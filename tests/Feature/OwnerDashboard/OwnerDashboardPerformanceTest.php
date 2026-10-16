<?php

namespace Tests\Feature\OwnerDashboard;

use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Tests\OwnerDashboardTestCase;

class OwnerDashboardPerformanceTest extends OwnerDashboardTestCase
{
    private function queries(callable $operation): int
    {
        Auth::forgetGuards();
        DB::enableQueryLog();
        DB::flushQueryLog();
        $operation();
        $count = count(DB::getQueryLog());
        DB::disableQueryLog();

        return $count;
    }

    public function test_queries_stay_bounded_with_fifty_and_two_hundred_products_and_multiple_requests(): void
    {
        $id = $this->requestFixture();
        $this->requestFixture();
        $this->requestFixture();
        $productId = DB::table('products')->first()->id;
        $this->ownerLogin();
        $measurements = [];
        foreach ([50, 200] as $target) {
            while (DB::table('products')->count() < $target) {
                $this->product(['name' => 'مرطب '.DB::table('products')->count()]);
            }
            $counts = [];
            foreach (['dashboard' => 'owner.dashboard', 'products' => 'owner.products.index', 'requests' => 'owner.requests.index'] as $key => $route) {
                $counts[$key] = $this->queries(fn () => $this->get(route($route))->assertOk());
            }
            $counts['search'] = $this->queries(fn () => $this->get(route('owner.search', ['q' => 'مرطب']))->assertOk());
            $counts['product_detail'] = $this->queries(fn () => $this->get(route('owner.products.show', ['id' => $productId]))->assertOk());
            $counts['request_detail'] = $this->queries(fn () => $this->get(route('owner.requests.show', ['id' => $id]))->assertOk());
            $measurements[$target] = $counts;
        }
        $this->assertSame($measurements[50], $measurements[200]);
        foreach ($measurements[200] as $name => $count) {
            // Phase6D adds two real SQL review aggregates only to the overview.
            $this->assertLessThanOrEqual($name === 'dashboard' ? 11 : 10, $count);
        }
        $plans = [
            'name_contains' => DB::select("EXPLAIN SELECT id, name FROM products WHERE name LIKE ? ESCAPE '!' ORDER BY name, id LIMIT 10", ['%مرطب%']),
            'product_status' => DB::select("EXPLAIN SELECT id FROM products WHERE status = 'active' ORDER BY created_at, id LIMIT 25"),
            'request_date' => DB::select('EXPLAIN SELECT id FROM checkout_intents WHERE created_at >= ? AND created_at < ? ORDER BY created_at, id LIMIT 25', ['2026-10-01 00:00:00', '2026-10-15 00:00:00']),
        ];
        fwrite(STDOUT, 'DASHBOARD_QUERY_COUNTS '.json_encode($measurements).' DASHBOARD_EXPLAIN '.json_encode($plans).PHP_EOL);
    }
}
