<?php

namespace Tests\Feature\Gifts;

use App\Domains\Gifts\Application\ManageGiftOccasion;
use App\Domains\Gifts\Application\ManageGiftPackage;
use App\Domains\Gifts\Application\ReviewGiftRequest;
use App\Domains\Gifts\Application\SubmitGiftRequest;
use App\Domains\Gifts\Infrastructure\GiftStore;
use App\Domains\Gifts\Infrastructure\PublishedGifts;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\GiftTestCase;

class GiftPerformanceTest extends GiftTestCase
{
    private function measured(callable $operation): int
    {
        DB::flushQueryLog();
        DB::enableQueryLog();
        try {
            $response = $operation();
            $this->assertLessThan(400, $response->status(), $response->getContent());

            return count(DB::getQueryLog());
        } finally {
            DB::disableQueryLog();
        }
    }

    public function test_http_query_counts_remain_bounded_with_composition_revisions_requests_and_audit_growth(): void
    {
        $this->ownerLogin();
        $empty = $this->measured(fn () => $this->getJson('/api/v1/gifts/packages'));
        $customer = $this->customer();
        $this->actingAs($customer, 'customer');
        $occasion = app(ManageGiftOccasion::class)->execute(null, $this->owner, $this->giftCommand(extra: ['values' => ['name' => 'مناسبة أداء', 'is_active' => '1']]));
        $product = $this->product();
        $id = $this->gift(['occasion_ids' => [$occasion], 'components' => [['type' => 'catalog', 'product_id' => $product->id, 'quantity' => 2, 'sort_order' => 0], ['type' => 'manual', 'name' => 'بطاقة', 'quantity' => 1, 'sort_order' => 1], ['type' => 'manual', 'name' => 'تغليف', 'quantity' => 1, 'sort_order' => 2]]]);
        $template = GiftStore::revision($id, '3');
        $slug = DB::table('gift_packages')->where('id', $id)->value('slug');
        $created = 1;
        $measurements = [];
        foreach ([10, 50, 200] as $volume) {
            // Relational synthetic fixtures grow every current list and child set without invoking unrelated image processing.
            while ($created < $volume) {
                $pid = (string) Str::uuid7();
                $rid = (string) Str::uuid7();
                DB::table('gift_packages')->insert(['id' => $pid, 'slug' => 'perf-'.$created, 'lock_version' => 1, 'created_at' => now(), 'updated_at' => now()]);
                DB::table('gift_package_revisions')->insert(GiftStore::values($template) + ['id' => $rid, 'package_id' => $pid, 'version' => 1, 'owner_id' => $this->owner->id, 'action' => 'publish', 'created_at' => now()]);
                foreach (['components', 'images', 'occasions'] as $kind) {
                    foreach ($template[$kind] as $row) {
                        unset($row['id'], $row['revision_id']);
                        DB::table('gift_revision_'.$kind)->insert(['id' => (string) Str::uuid7(), 'revision_id' => $rid] + $row);
                    }
                }
                DB::table('gift_packages')->where('id', $pid)->update(['published_version' => 1]);
                $created++;
            }
            for ($n = 0; $n < 10; $n++) {
                $version = (string) DB::table('gift_packages')->where('id', $id)->value('lock_version');
                app(ManageGiftPackage::class)->execute($id, $this->owner, $this->giftCommand($version, extra: ['values' => ['short_description' => 'مسودة أداء '.$volume.'-'.$n]]));
                app(SubmitGiftRequest::class)->execute($customer->id, $this->giftInput($id));
            }
            $rid = DB::table('gift_requests')->value('id');
            for ($n = 0; $n < 10; $n++) {
                $version = (string) (DB::table('gift_request_reviews')->where('request_id', $rid)->value('lock_version') ?? '0');
                app(ReviewGiftRequest::class)->execute($rid, $this->owner, ['operation_id' => (string) Str::uuid7(), 'expected_version' => $version, 'kind' => 'note', 'note' => 'سجل أداء']);
            }
            $counts = [];
            $counts['public_list'] = $this->measured(fn () => $this->getJson('/api/v1/gifts/packages'));
            $counts['occasion_list'] = $this->measured(fn () => $this->getJson('/api/v1/gifts/packages?occasion_id='.$occasion));
            $counts['public_detail'] = $this->measured(fn () => $this->getJson('/api/v1/gifts/packages/'.$slug));
            $counts['owner_list'] = $this->measured(fn () => $this->get(route('owner.gifts.index')));
            $counts['owner_edit_composition'] = $this->measured(fn () => $this->get(route('owner.gifts.edit', ['id' => $id])));
            $counts['customer_history'] = $this->measured(fn () => $this->getJson('/api/v1/gifts/requests'));
            $counts['owner_requests'] = $this->measured(fn () => $this->get(route('owner.gifts.requests')));
            $counts['owner_detail_timeline'] = $this->measured(fn () => $this->get(route('owner.gifts.requests.show', ['id' => $rid])));
            $counts['audit'] = $this->measured(fn () => $this->get(route('owner.gifts.history')));
            $version = (string) DB::table('gift_packages')->where('id', $id)->value('lock_version');
            $counts['publish'] = $this->measured(fn () => $this->post(route('owner.gifts.mutate', ['id' => $id]), $this->giftCommand($version, 'publish')));
            $counts['create_request'] = $this->measured(fn () => $this->postJson('/api/v1/gifts/requests', $this->giftInput($id)));
            $measurements[$volume] = $counts;
        }
        $this->assertSame($measurements[10], $measurements[50]);
        $this->assertSame($measurements[10], $measurements[200]);
        $plans = ['customer' => DB::select('EXPLAIN SELECT id FROM gift_requests WHERE customer_id = ? ORDER BY created_at DESC,id LIMIT 25', [$customer->id]), 'events' => DB::select('EXPLAIN SELECT id FROM gift_review_events WHERE request_id = ? ORDER BY created_at DESC,id DESC LIMIT 25', [$rid]), 'revision' => DB::select('EXPLAIN SELECT id FROM gift_package_revisions WHERE package_id = ? AND version = ?', [$id, '3']), 'media' => DB::select('EXPLAIN SELECT revision_id FROM gift_revision_images WHERE media_id = ?', [$template['images'][0]['media_id']])];
        $publicQuery = app(PublishedGifts::class)->query()->orderBy('r.sort_order')->orderBy('p.id')->limit(12);
        $plans['public_list'] = DB::select('EXPLAIN '.$publicQuery->toSql(), $publicQuery->getBindings());
        $countQuery = app(PublishedGifts::class)->query()->select(DB::raw('COUNT(*) AS aggregate'));
        $plans['public_count'] = DB::select('EXPLAIN '.$countQuery->toSql(), $countQuery->getBindings());
        $plans['occasion_filter'] = DB::select('EXPLAIN SELECT revision_id FROM gift_revision_occasions WHERE occasion_id = ?', [$occasion]);
        foreach (['public_list', 'public_count'] as $key) {
            $text = json_encode($plans[$key], JSON_THROW_ON_ERROR);
            $this->assertMatchesRegularExpression('/DEPENDENT SUBQUERY|subquery in condition; dependent/i', $text);
            $unbounded = preg_match('/Materialize with deduplication|Table scan on (?:c|r) /i', $text) === 1;
            foreach ($plans[$key] as $row) {
                $unbounded = $unbounded || (($row->select_type ?? '') === 'MATERIALIZED') || (in_array($row->table ?? '', ['c', 'r'], true) && ($row->type ?? '') === 'ALL');
            }
            $this->assertFalse($unbounded, 'Public page/count must probe current revisions, not scan/materialize unrelated component history.');
        }
        foreach ($plans as $plan) {
            $this->assertNotEmpty($plan);
        }
        fwrite(STDOUT, 'GIFT_HTTP_COUNTS '.json_encode(['empty' => $empty, 'volumes' => $measurements, 'plans' => $plans], JSON_THROW_ON_ERROR).PHP_EOL);
    }
}
