<?php

namespace App\Domains\Gifts\Application;

use App\Domains\Gifts\Domain\GiftRules;
use App\Domains\Gifts\Infrastructure\GiftCatalog;
use App\Domains\Gifts\Infrastructure\GiftStore;
use App\Domains\Identity\Domain\Enums\CustomerAccountStatus;
use App\Domains\Identity\Domain\Exceptions\AccountSuspended;
use App\Domains\Identity\Infrastructure\Persistence\Models\Customer;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

final class SubmitGiftRequest
{
    public function input(array $input): array
    {
        GiftInput::only($input, ['operation_id', 'package_id', 'expected_published_version', 'currency', 'occasion_id', 'sender_display_name', 'message', 'recipient']);
        $op = GiftInput::operation($input['operation_id'] ?? null);
        $version = GiftInput::version($input['expected_published_version'] ?? null, advancing: false);
        $v = validator($input, ['package_id' => 'required|uuid', 'currency' => 'required|in:USD,SYP', 'occasion_id' => 'nullable|uuid', 'recipient' => 'required|array'])->validate();
        $recipient = GiftInput::text($v['recipient'], ['full_name' => ['المستلم', 150], 'phone' => ['الهاتف', 40], 'governorate' => ['المحافظة', 120], 'city' => ['المدينة', 120], 'address' => ['العنوان', 500], 'notes' => ['ملاحظات', 1000]]);
        foreach (['full_name', 'phone', 'governorate', 'city', 'address'] as $key) {
            abort_unless(isset($recipient[$key]) && preg_match('/[\p{L}\p{N}]/u', $recipient[$key]), 422, 'أكملي بيانات المستلم.');
        }
        abort_unless(preg_match('/^\+?[\p{Nd} ().\/-]+$/uD', $recipient['phone']) && preg_match('/(?:\p{Nd}[^\p{Nd}]*){3}/u', $recipient['phone']), 422, 'الهاتف غير صالح.');
        $text = GiftInput::text(array_intersect_key($input, ['sender_display_name' => true, 'message' => true]), ['sender_display_name' => ['المرسل', 150], 'message' => ['الرسالة', 1000]]);
        // Explicit stable key order makes normalization independent of client object ordering.
        $result = ['operation_id' => $op, 'package_id' => strtolower($v['package_id']), 'expected_published_version' => $version, 'currency' => $v['currency'], 'occasion_id' => isset($v['occasion_id']) ? strtolower($v['occasion_id']) : null, 'sender_display_name' => $text['sender_display_name'] ?? null, 'message' => $text['message'] ?? null];
        $result['recipient'] = [];
        foreach (['full_name', 'phone', 'governorate', 'city', 'address', 'notes'] as $key) {
            $result['recipient'][$key] = $recipient[$key] ?? null;
        }

        return $result;
    }

    public function execute(string $customerId, array $input): array
    {
        GiftStore::ready();
        $data = $this->input($input);
        $fingerprint = GiftInput::fingerprint(array_diff_key($data, ['operation_id' => true]));

        return DB::transaction(function () use ($customerId, $data, $fingerprint): array {
            $customer = Customer::query()->whereKey($customerId)->lockForUpdate()->firstOrFail();
            if ($customer->account_status !== CustomerAccountStatus::Active) {
                throw new AccountSuspended;
            }
            $prior = DB::table('gift_requests')->where('customer_id', $customerId)->where('operation_id', $data['operation_id'])->lockForUpdate()->first();
            if ($prior) {
                abort_unless(hash_equals($prior->payload_fingerprint, $fingerprint), 409);

                return ['id' => $prior->id, 'created' => false];
            }
            abort_unless(strlen($customer->email) <= 255 && filter_var($customer->email, FILTER_VALIDATE_EMAIL), 409);
            $p = DB::table('gift_packages')->where('id', $data['package_id'])->lockForUpdate()->first();
            abort_unless($p && $p->archived_at === null && $p->published_version !== null, 409, 'الباقة غير متاحة لطلب جديد.');
            abort_unless((string) $p->published_version === $data['expected_published_version'], 409, 'تغير الإصدار المنشور؛ حدّثي العرض.');
            $r = GiftStore::revision($p->id, (string) $p->published_version);
            $occasion = null;
            if ($data['occasion_id'] !== null) {
                $occasion = DB::table('gift_occasions')->where('id', $data['occasion_id'])->lockForUpdate()->first();
                $association = collect($r['occasions'])->firstWhere('occasion_id', $data['occasion_id']);
                abort_unless($occasion && $occasion->is_active && $occasion->archived_at === null && $association, 409, 'المناسبة غير متاحة.');
                $occasion = $association['name'];
            }
            $eligibility = app(GiftCatalog::class)->inspect($r['components'], $r['availability'], true);
            $price = $r[$data['currency'] === 'USD' ? 'price_usd' : 'price_syp'];
            abort_unless($eligibility['eligible'] && GiftRules::positive($price), 409, 'الباقة أو سعر العملة غير متاح لطلب جديد.');
            $id = (string) Str::uuid7();
            DB::table('gift_requests')->insert(['id' => $id, 'customer_id' => $customerId, 'operation_id' => $data['operation_id'], 'payload_fingerprint' => $fingerprint, 'package_id' => $p->id, 'published_version' => $r['version'], 'package_name' => $r['name'], 'currency' => $data['currency'], 'display_price_snapshot' => $price, 'requires_fulfillment_review' => $eligibility['review'], 'occasion_id' => $data['occasion_id'], 'occasion_name' => $occasion, 'account_email' => $customer->email, 'sender_display_name' => $data['sender_display_name'], 'message' => $data['message'],
                'recipient_name' => $data['recipient']['full_name'], 'recipient_phone' => $data['recipient']['phone'], 'recipient_governorate' => $data['recipient']['governorate'], 'recipient_city' => $data['recipient']['city'], 'recipient_address' => $data['recipient']['address'], 'recipient_notes' => $data['recipient']['notes'], 'created_at' => now()]);
            $rows = [];
            foreach ($r['components'] as $c) {
                $rows[] = array_intersect_key($c, array_flip(['type', 'product_id', 'variant_id', 'name', 'variant_name', 'description', 'quantity', 'sort_order'])) + ['id' => (string) Str::uuid7(), 'request_id' => $id];
            }
            DB::table('gift_request_components')->insert($rows);

            return ['id' => $id, 'created' => true];
        }, 3);
    }
}
