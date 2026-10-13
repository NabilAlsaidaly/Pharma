<?php

namespace App\Domains\Orders\Application;

use App\Domains\Cart\Application\CartSnapshot;
use App\Domains\Cart\Domain\Exceptions\CartConflict;
use App\Domains\Identity\Domain\Enums\CustomerAccountStatus;
use App\Domains\Identity\Domain\Exceptions\AccountSuspended;
use App\Domains\Identity\Infrastructure\Persistence\Models\Customer;
use App\Domains\Orders\Infrastructure\Persistence\Models\CheckoutIntent;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class SubmitPreliminaryCheckoutRequest
{
    public function __construct(private readonly CartSnapshot $cart) {}

    public function execute(string $customerId, array $input): array
    {
        $contact = $input['contact'];
        $canonical = ['currency' => $input['currency'], 'expected_cart_version' => $input['expected_cart_version']];
        foreach (['full_name', 'phone', 'governorate', 'city', 'address', 'notes'] as $key) {
            $canonical[$key] = $contact[$key] ?? null;
        }
        $fingerprint = hash('sha256', json_encode($canonical, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));

        return DB::transaction(function () use ($customerId, $input, $contact, $fingerprint) {
            $customer = Customer::query()->whereKey($customerId)->lockForUpdate()->firstOrFail();
            if ($customer->account_status !== CustomerAccountStatus::Active) {
                throw new AccountSuspended;
            }
            $existing = CheckoutIntent::query()->where('customer_id', $customerId)->where('operation_id', $input['operation_id'])->lockForUpdate()->first();
            if ($existing !== null) {
                if (! hash_equals($existing->request_fingerprint, $fingerprint)) {
                    throw new CartConflict('operation_payload_conflict');
                }

                return ['intent' => $existing->loadCount('items'), 'created' => false];
            }
            if (strlen($customer->email) > 255 || ! filter_var($customer->email, FILTER_VALIDATE_EMAIL)) {
                throw new CartConflict('customer_email_unavailable');
            }
            $source = $this->cart->lockedForSubmission($customerId, $input['expected_cart_version'], $input['currency']);
            $intent = CheckoutIntent::query()->create(['customer_id' => $customerId, 'cart_id' => $source['cart_id'],
                'source_cart_version' => $source['version'], 'operation_id' => $input['operation_id'], 'request_fingerprint' => $fingerprint,
                'currency' => $input['currency'], 'status' => 'submitted', 'email' => $customer->email] + $contact);
            $rows = [];
            foreach ($source['items'] as $order => $item) {
                $rows[] = ['id' => (string) Str::uuid7(), 'checkout_intent_id' => $intent->id, 'line_order' => $order + 1] + $item;
            }
            DB::table('checkout_intent_items')->insert($rows);

            return ['intent' => $intent->loadCount('items'), 'created' => true];
        }, 3);
    }
}
