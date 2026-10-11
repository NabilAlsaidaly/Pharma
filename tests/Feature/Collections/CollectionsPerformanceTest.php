<?php

namespace Tests\Feature\Collections;

use App\Domains\Cart\Application\CartOperations;
use App\Domains\Cart\Application\CartSnapshot;
use App\Domains\Favorites\Application\FavoriteOperations;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Tests\PersonalCollectionsTestCase;

class CollectionsPerformanceTest extends PersonalCollectionsTestCase
{
    private function countQueries(callable $operation): int
    {
        DB::flushQueryLog();
        DB::enableQueryLog();
        Model::preventLazyLoading();
        try {
            $operation();

            return count(DB::getQueryLog());
        } finally {
            DB::disableQueryLog();
            Model::preventLazyLoading(false);
        }
    }

    public function test_favorites_and_cart_reads_do_not_grow_with_item_count_or_relationships(): void
    {
        $counts = [];
        for ($i = 1; $i <= 50; $i++) {
            $p = $this->product();
            $v = $this->variant($p, ['price_usd' => '2']);
            $this->image($p);
            app(FavoriteOperations::class)->add($this->owner->id, $p->id);
            $this->addItem($p, $v->id);
            if (in_array($i, [2, 50], true)) {
                $counts['favorites_'.$i] = $this->countQueries(fn () => $this->getJson('/api/v1/favorites')->assertOk());
                $counts['cart_'.$i] = $this->countQueries(fn () => $this->getJson('/api/v1/cart')->assertOk()->assertJsonPath('data.totals.USD.estimated_total', number_format($i * 2, 2, '.', '')));
            }
        }
        $this->assertSame($counts['favorites_2'], $counts['favorites_50']);
        $this->assertSame($counts['cart_2'], $counts['cart_50']);
        $this->assertLessThanOrEqual(8, $counts['cart_50']);
        fwrite(STDOUT, 'COLLECTION_READ_COUNTS '.json_encode($counts).PHP_EOL);
    }

    public function test_measure_membership_all_mutations_and_current_totals_query_plans(): void
    {
        $p = $this->product();
        $a = $this->variant($p, ['price_usd' => '2']);
        $b = $this->variant($p, ['price_usd' => '3']);
        $fav = app(FavoriteOperations::class);
        $ops = app(CartOperations::class);
        $counts = [];
        $counts['favorite_add'] = $this->countQueries(fn () => $fav->add($this->owner->id, $p->id));
        $counts['favorite_check'] = $this->countQueries(fn () => $fav->check($this->owner->id, $p->id));
        $counts['favorite_remove'] = $this->countQueries(fn () => $fav->remove($this->owner->id, $p->id));
        $r = null;
        $counts['cart_add'] = $this->countQueries(function () use (&$r, $p, $a) {
            $r = $this->addItem($p, $a->id);
        });
        $id = $r['item']['id'];
        $counts['quantity_update'] = $this->countQueries(fn () => $ops->update($this->owner->id, $id, ['quantity' => 2, 'expected_version' => '1']));
        $counts['variant_change'] = $this->countQueries(fn () => $ops->update($this->owner->id, $id, ['variant_id' => $b->id, 'expected_version' => '2']));
        $counts['totals_read'] = $this->countQueries(fn () => app(CartSnapshot::class)->read($this->owner->id, 1, 12));
        $counts['cart_remove'] = $this->countQueries(fn () => $ops->remove($this->owner->id, $id));
        foreach ($counts as $count) {
            $this->assertLessThanOrEqual(15, $count);
        }
        $plan = DB::select('EXPLAIN FORMAT=TRADITIONAL SELECT id FROM carts WHERE customer_id = ?', [$this->owner->id]);
        $this->assertNotNull($plan[0]->key);
        fwrite(STDOUT, 'COLLECTION_MUTATION_COUNTS '.json_encode($counts).PHP_EOL);
        fwrite(STDOUT, 'COLLECTION_EXPLAIN '.json_encode($plan).PHP_EOL);
    }
}
