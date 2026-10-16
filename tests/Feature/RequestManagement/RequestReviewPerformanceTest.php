<?php

namespace Tests\Feature\RequestManagement;

use App\Domains\Catalog\Infrastructure\Persistence\Models\Product;
use App\Domains\Orders\Application\Review\ReviewRequestAction;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\OwnerDashboardTestCase;

class RequestReviewPerformanceTest extends OwnerDashboardTestCase
{
    private function measured(callable $work): int
    {
        Auth::forgetGuards();
        DB::enableQueryLog();
        DB::flushQueryLog();
        try {
            $work();

            return count(DB::getQueryLog());
        } finally {
            DB::disableQueryLog();
            DB::flushQueryLog();
        }
    }

    public function test_ten_real_http_paths_at_zero_fifty_two_hundred_and_one_thousand_requests(): void
    {
        $this->ownerLogin();
        $empty = $this->measured(fn () => $this->get(route('owner.requests.index'))->assertOk());
        $id = $this->requestFixture();
        $header = (array) DB::table('checkout_intents')->where('id', $id)->first();
        $item = (array) DB::table('checkout_intent_items')->where('checkout_intent_id', $id)->first();
        unset($item['selection_key']);
        for ($i = 0; $i < 28; $i++) {
            app(ReviewRequestAction::class)->execute($id, $this->owner, (string) Str::uuid7(), (string) $i, 'note', note: 'ملاحظة قياس '.$i);
        }
        app(ReviewRequestAction::class)->execute($id, $this->owner, (string) Str::uuid7(), '28', 'transition', 'under_review');
        // Retained actual request references to an archived Product remain readable.
        Product::query()->whereKey($item['product_id'])->firstOrFail()->delete();
        $measurements = [];
        foreach ([50, 200, 1000] as $target) {
            $current = DB::table('checkout_intents')->count();
            $headers = [];
            $items = [];
            for ($i = $current; $i < $target; $i++) {
                $next = (string) Str::uuid7();
                $headers[] = array_replace($header, ['id' => $next, 'operation_id' => (string) Str::uuid7()]);
                for ($line = 1; $line <= 2; $line++) {
                    $items[] = array_replace($item, ['id' => (string) Str::uuid7(), 'checkout_intent_id' => $next, 'source_cart_item_id' => (string) Str::uuid7(), 'line_order' => $line,
                        'product_id' => $line === 1 ? $item['product_id'] : $this->product(['price_usd' => '1'])->id, 'requires_fulfillment_review' => $i % 2]);
                }
            }
            foreach (array_chunk($headers, 100) as $chunk) {
                DB::table('checkout_intents')->insert($chunk);
            }
            foreach (array_chunk($items, 100) as $chunk) {
                DB::table('checkout_intent_items')->insert($chunk);
            }
            // Representative persisted review states as well as inferred new rows.
            $sample = DB::table('checkout_intents')->where('id', '<>', $id)->limit(3)->pluck('id')->all();
            foreach ($sample as $sampleId) {
                if (! DB::table('preliminary_request_reviews')->where('checkout_intent_id', $sampleId)->exists()) {
                    app(ReviewRequestAction::class)->execute($sampleId, $this->owner, (string) Str::uuid7(), '0', 'transition', 'awaiting_contact');
                }
            }
            $counts = [];
            foreach (['list' => route('owner.requests.index'), 'filtered' => route('owner.requests.index', ['review_status' => 'new', 'fulfillment' => '1']), 'detail' => route('owner.requests.show', ['id' => $id]),
                'timeline' => route('owner.requests.show', ['id' => $id, 'events_page' => 2]), 'overview' => route('owner.dashboard'), 'search' => route('owner.search', ['q' => $id])] as $key => $url) {
                $counts[$key] = $this->measured(fn () => $this->get($url)->assertOk());
            }
            $version = (string) DB::table('preliminary_request_reviews')->where('checkout_intent_id', $id)->value('lock_version');
            $counts['note'] = $this->measured(fn () => $this->post(route('owner.requests.review', ['id' => $id]), ['operation_id' => (string) Str::uuid7(), 'expected_version' => $version, 'kind' => 'note', 'note' => 'قياس كتابة'])->assertRedirect());
            $version = (string) ((int) $version + 1);
            $counts['transition'] = $this->measured(fn () => $this->post(route('owner.requests.review', ['id' => $id]), ['operation_id' => (string) Str::uuid7(), 'expected_version' => $version, 'kind' => 'transition', 'target' => 'awaiting_contact'])->assertRedirect());
            $counts['preview'] = $this->measured(fn () => $this->post(route('owner.requests.preview'), ['scope' => 'ids', 'ids' => [$id], 'target' => 'under_review'])->assertOk());
            $counts['execute'] = $this->measured(fn () => $this->post(route('owner.requests.confirm'), ['token' => session('request_review_confirmation.token'), 'confirm' => '1'])->assertRedirect());
            $measurements[$target] = $counts;
        }
        $this->assertSame($measurements[50], $measurements[200]);
        $this->assertSame($measurements[50], $measurements[1000]);
        foreach ($measurements[1000] as $key => $count) {
            $this->assertLessThanOrEqual(25, $count, $key);
        }
        $plans = DB::select('EXPLAIN FORMAT=TRADITIONAL SELECT id FROM checkout_intents WHERE created_at >= ? AND created_at < ? ORDER BY created_at,id LIMIT 25', [now()->startOfDay()->format('Y-m-d H:i:s'), now()->addDay()->startOfDay()->format('Y-m-d H:i:s')]);
        fwrite(STDOUT, 'REVIEW_HTTP_COUNTS '.json_encode(['empty_list' => $empty, 'volumes' => $measurements, 'owner_date_explain' => $plans], JSON_THROW_ON_ERROR).PHP_EOL);
    }
}
