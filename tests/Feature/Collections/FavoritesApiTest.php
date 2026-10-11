<?php

namespace Tests\Feature\Collections;

use App\Domains\Identity\Domain\Enums\CustomerAccountStatus;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\PersonalCollectionsTestCase;

class FavoritesApiTest extends PersonalCollectionsTestCase
{
    public function test_guest_and_bearer_only_cannot_access_any_favorite_operation(): void
    {
        $this->app['auth']->forgetGuards();
        $this->withHeader('Authorization', 'Bearer fake');
        foreach (['GET /api/v1/favorites', 'POST /api/v1/favorites', 'GET /api/v1/favorites/check', 'DELETE /api/v1/favorites/'.Str::uuid7()] as $route) {
            [$method,$url] = explode(' ', $route, 2);
            $this->json($method, $url)->assertUnauthorized()->assertJsonPath('error.code', 'UNAUTHENTICATED');
        }
    }

    public function test_suspended_customer_denied(): void
    {
        $this->owner->account_status = CustomerAccountStatus::Suspended;
        $this->owner->save();
        $this->getJson('/api/v1/favorites')->assertForbidden()->assertJsonPath('error.code', 'ACCOUNT_SUSPENDED');
    }

    public function test_add_check_list_remove_and_repeated_add_are_idempotent(): void
    {
        $p = $this->product();
        $this->postJson('/api/v1/favorites', ['product_id' => $p->id])->assertCreated()->assertJsonPath('data.is_favorite', true);
        $time = DB::table('favorites')->sole()->created_at;
        $this->postJson('/api/v1/favorites', ['product_id' => $p->id])->assertOk();
        $this->assertSame(1, DB::table('favorites')->count());
        $this->assertSame($time, DB::table('favorites')->sole()->created_at);
        $this->getJson('/api/v1/favorites/check?product_id='.$p->id)->assertOk()->assertJsonPath('data.is_favorite', true);
        $this->getJson('/api/v1/favorites')->assertOk()->assertJsonCount(1, 'data');
        $this->deleteJson('/api/v1/favorites/'.$p->id)->assertOk();
        $this->deleteJson('/api/v1/favorites/'.$p->id)->assertOk();
        $this->getJson('/api/v1/favorites/check?product_id='.$p->id)->assertJsonPath('data.is_favorite', false);
    }

    public function test_hidden_product_retained_not_counted_and_membership_matches_unknown_then_reappears(): void
    {
        $p = $this->product();
        $this->postJson('/api/v1/favorites', ['product_id' => $p->id]);
        $p->update(['status' => 'hidden']);
        $this->getJson('/api/v1/favorites')->assertJsonCount(0, 'data')->assertJsonPath('meta.total', 0);
        foreach ([$p->id, (string) Str::uuid7()] as $id) {
            $this->getJson('/api/v1/favorites/check?product_id='.$id)->assertNotFound()->assertJsonPath('error.code', 'RESOURCE_NOT_FOUND');
        }
        $this->assertSame(1, DB::table('favorites')->count());
        $p->update(['status' => 'active']);
        $this->getJson('/api/v1/favorites')->assertJsonCount(1, 'data');
    }

    public function test_hidden_brand_blocks_list_add_and_check_without_deleting_saved_relation(): void
    {
        $b = $this->brand();
        $p = $this->product([], $b);
        $this->postJson('/api/v1/favorites', ['product_id' => $p->id]);
        $b->update(['status' => 'hidden']);
        $this->getJson('/api/v1/favorites')->assertJsonCount(0, 'data');
        $this->postJson('/api/v1/favorites', ['product_id' => $p->id])->assertNotFound();
        $this->getJson('/api/v1/favorites/check?product_id='.$p->id)->assertNotFound();
        $this->deleteJson('/api/v1/favorites/'.$p->id)->assertOk();
        $this->assertSame(0, DB::table('favorites')->count());
    }

    public function test_owner_isolation_on_read_remove_membership_and_forged_fields(): void
    {
        $p = $this->product();
        $this->postJson('/api/v1/favorites', ['product_id' => $p->id]);
        $other = $this->otherCustomer();
        $this->actingAs($other, 'customer');
        $this->getJson('/api/v1/favorites')->assertJsonCount(0, 'data');
        $this->getJson('/api/v1/favorites/check?product_id='.$p->id)->assertJsonPath('data.is_favorite', false);
        $this->deleteJson('/api/v1/favorites/'.$p->id)->assertOk();
        $this->assertSame(1, DB::table('favorites')->count());
        $this->postJson('/api/v1/favorites', ['product_id' => $p->id, 'customer_id' => $this->owner->id])->assertUnprocessable();
        $this->getJson('/api/v1/favorites?customer_id='.$this->owner->id)->assertUnprocessable();
    }

    public function test_unpriced_unavailable_and_variant_products_can_be_saved_at_product_level(): void
    {
        $p = $this->product(['availability_status' => 'unavailable']);
        $this->variant($p);
        $this->postJson('/api/v1/favorites', ['product_id' => $p->id])->assertCreated();
        $this->getJson('/api/v1/favorites')->assertJsonPath('data.0.pricing.price_usd', null);
        $this->postJson('/api/v1/favorites', ['product_id' => $p->id, 'variant_id' => Str::uuid7()])->assertUnprocessable();
    }

    public function test_pagination_bounds_and_stable_order(): void
    {
        for ($i = 0; $i < 14; $i++) {
            $p = $this->product();
            $this->postJson('/api/v1/favorites', ['product_id' => $p->id]);
        }
        $first = $this->getJson('/api/v1/favorites?per_page=2')->assertJsonCount(2, 'data')->assertJsonPath('meta.total', 14)->json('data');
        $this->assertSame($first, $this->getJson('/api/v1/favorites?per_page=2')->json('data'));
        $this->getJson('/api/v1/favorites?page=500')->assertJsonCount(0,'data');
        $this->getJson('/api/v1/favorites?per_page=49')->assertUnprocessable();
    }
}
