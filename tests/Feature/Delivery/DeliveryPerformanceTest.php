<?php

namespace Tests\Feature\Delivery;

use App\Domains\Settings\Infrastructure\PublishedDeliveryZones;
use Illuminate\Support\Facades\DB;
use Tests\DeliveryTestCase;

class DeliveryPerformanceTest extends DeliveryTestCase
{
    private function measure(string $url): int
    {
        DB::flushQueryLog();
        DB::enableQueryLog();
        try {
            $this->get($url, ['Accept' => str_starts_with($url, '/api/') ? 'application/json' : 'text/html'])->assertOk();

            return count(DB::getQueryLog());
        } finally {
            DB::disableQueryLog();
        }
    }

    public function test_bounded_lists_zero_one_ten_fifty_and_real_index_plans(): void
    {
        $this->ownerLogin();
        $counts = ['empty' => $this->measure('/api/v1/delivery/zones'), 'volumes' => []];
        $previous = 0;
        $firstZone = null;
        $firstWorker = null;
        foreach ([1, 10, 50] as $size) {
            for ($i = $previous; $i < $size; $i++) {
                $zone = $this->zone(['city' => 'منطقة '.$i, 'fee_usd' => $i % 2 ? '2.50' : null, 'fee_syp' => $i % 3 ? null : '0.00']);
                $worker = $this->worker(['name' => 'عامل '.$i, 'phone' => '123456', 'is_active' => (bool) ($i % 2)]);
                $firstZone ??= $zone;
                $firstWorker ??= $worker;
            }
            $previous = $size;
            // History growth must not change list query count.
            $version = (string) DB::table('store_delivery_zones')->where('id', $firstZone)->value('lock_version');
            $this->setting('zones', ['conditions' => str_repeat('ملاحظة خاصة ', 10)], $version, $firstZone);
            $counts['volumes'][$size] = ['public' => $this->measure('/api/v1/delivery/zones'), 'owner' => $this->measure(route('owner.delivery.index')), 'search' => $this->measure(route('owner.delivery.index', ['q' => 'عامل'])), 'edit' => $this->measure(route('owner.delivery.edit', ['id' => $firstWorker])), 'history' => $this->measure(route('owner.delivery.history')), 'worker_history' => $this->measure(route('owner.delivery.history', ['worker' => $firstWorker]))];
        }
        $this->assertSame($counts['volumes'][1], $counts['volumes'][10]);
        $this->assertSame($counts['volumes'][10], $counts['volumes'][50]);
        $this->getJson('/api/v1/delivery/zones?per_page=48')->assertJsonCount(48, 'data')->assertJsonPath('meta.total', 50);
        $this->getJson('/api/v1/delivery/zones?per_page=48&page=2')->assertJsonCount(2, 'data');
        $query = app(PublishedDeliveryZones::class)->query()->select('z.id')->orderBy('r.sort_order')->orderBy('z.id')->limit(12);
        $count = app(PublishedDeliveryZones::class)->query()->selectRaw('COUNT(*)');
        $plans = ['zones' => DB::select('EXPLAIN '.$query->toSql(), $query->getBindings()), 'zone_count' => DB::select('EXPLAIN '.$count->toSql(), $count->getBindings()), 'workers' => DB::select('EXPLAIN SELECT id,name,phone FROM delivery_workers WHERE is_active=1 ORDER BY name,id LIMIT 25'), 'history' => DB::select('EXPLAIN SELECT action FROM delivery_worker_changes WHERE worker_id=? ORDER BY created_at DESC,id DESC LIMIT 25', [$firstWorker])];
        foreach ($plans as $plan) {
            $this->assertNotEmpty($plan);
        }
        fwrite(STDOUT, 'DELIVERY_HTTP_COUNTS '.json_encode(['counts' => $counts, 'plans' => $plans], JSON_THROW_ON_ERROR).PHP_EOL);
    }
}
