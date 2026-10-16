<?php

namespace App\Domains\Gifts\Infrastructure;

use App\Domains\Gifts\Application\GiftInput;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

final class GiftRequests
{
    public function listing(array $filters, ?string $customer = null)
    {
        GiftStore::ready();
        $q = DB::table('gift_requests as g')->select(['g.id', 'g.status', 'g.package_name', 'g.currency', 'g.display_price_snapshot', 'g.requires_fulfillment_review', 'g.created_at']);
        if ($customer !== null) {
            $q->where('g.customer_id', $customer);
        } else {
            $q->leftJoin('gift_request_reviews as r', 'r.request_id', '=', 'g.id')->selectRaw("COALESCE(r.review_status, 'new') as review_status");
            if (isset($filters['review_status'])) {
                $q->whereRaw("COALESCE(r.review_status, 'new') = ?", [$filters['review_status']]);
            }
            if (isset($filters['q'])) {
                $q->where('g.id', 'like', str_replace(['!', '%', '_'], ['!!', '!%', '!_'], $filters['q']).'%');
            }
            if (isset($filters['currency'])) {
                $q->where('g.currency', $filters['currency']);
            }
            if (isset($filters['from'], $filters['to'])) {
                $q->where('g.created_at', '>=', CarbonImmutable::parse($filters['from'], config('app.timezone'))->startOfDay()->utc())->where('g.created_at', '<', CarbonImmutable::parse($filters['to'], config('app.timezone'))->addDay()->startOfDay()->utc());
            }
        }

        return $q->orderByDesc('g.created_at')->orderBy('g.id')->paginate($filters['per_page'] ?? 25, ['*'], 'page', $filters['page'] ?? 1);
    }

    public function detail(string $id, ?string $customer = null): array
    {
        GiftStore::ready();

        return DB::transaction(function () use ($id, $customer): array {
            $q = DB::table('gift_requests')->where('id', $id);
            if ($customer !== null) {
                $q->where('customer_id', $customer);
            }
            $r = $q->first();
            abort_if(! $r, 404);
            $data = (array) $r;
            $data['published_version'] = (string) $r->published_version;
            $data['requires_fulfillment_review'] = (bool) $r->requires_fulfillment_review;
            unset($data['payload_fingerprint'], $data['operation_id'], $data['customer_id']);
            $data['components'] = DB::table('gift_request_components')->where('request_id', $id)->orderBy('sort_order')->orderBy('id')->get(['type', 'product_id', 'variant_id', 'name', 'variant_name', 'description', 'quantity', 'sort_order'])->all();
            if ($customer === null) {
                $data['review'] = DB::table('gift_request_reviews')->where('request_id', $id)->first();
            }

            return $data;
        });
    }

    public static function filters(array $input, bool $owner = false): array
    {
        GiftInput::only($input, $owner ? ['page', 'per_page', 'q', 'currency', 'review_status', 'from', 'to'] : ['page', 'per_page']);
        foreach (['q', 'currency', 'review_status', 'from', 'to'] as $optional) {
            if (array_key_exists($optional, $input) && ($input[$optional] === '' || $input[$optional] === null)) {
                unset($input[$optional]);
            }
        }
        $rules = ['page' => 'sometimes|integer|between:1,1000', 'per_page' => 'sometimes|integer|in:12,25,48'];
        if ($owner) {
            $rules += ['q' => ['sometimes', 'string', 'regex:/^[a-fA-F0-9-]{2,36}$/D'], 'currency' => 'sometimes|in:USD,SYP', 'review_status' => 'sometimes|in:new,under_review,awaiting_contact,reviewed', 'from' => 'required_with:to|date_format:Y-m-d', 'to' => 'required_with:from|date_format:Y-m-d|after_or_equal:from|before_or_equal:today'];
        }
        $v = validator($input, $rules)->validate();
        if (isset($v['from'], $v['to'])) {
            abort_if(CarbonImmutable::parse($v['from'])->diffInDays(CarbonImmutable::parse($v['to'])) > 93, 422);
        }

        return $v;
    }
}
