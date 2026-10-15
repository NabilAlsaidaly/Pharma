<?php

namespace Tests\Feature\CatalogAdministration;

use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\OwnerDashboardTestCase;

class CatalogSecurityTest extends OwnerDashboardTestCase
{
    private function destinations(): array
    {
        $id = (string) Str::uuid7();

        return [
            ['GET', 'owner.catalog.create', ['type' => 'products']], ['POST', 'owner.catalog.store', ['type' => 'products']],
            ['GET', 'owner.catalog.edit', ['type' => 'products', 'id' => $id]], ['PATCH', 'owner.catalog.update', ['type' => 'products', 'id' => $id]],
            ['GET', 'owner.catalog.recycle', ['type' => 'products']], ['POST', 'owner.catalog.preview', ['type' => 'products']],
            ['POST', 'owner.catalog.execute', []], ['POST', 'owner.catalog.upload', ['type' => 'products', 'id' => $id]],
            ['PATCH', 'owner.catalog.image', ['id' => $id, 'image' => $id]],
        ];
    }

    public function test_every_catalog_endpoint_denies_guest_and_customer_sessions_without_business_writes(): void
    {
        foreach ($this->destinations() as [$method, $route, $parameters]) {
            $this->call($method, route($route, $parameters))->assertRedirect(route('owner.login'));
        }
        $this->actingAs($this->customer(), 'customer');
        foreach ($this->destinations() as [$method, $route, $parameters]) {
            $this->call($method, route($route, $parameters))->assertRedirect(route('owner.login'));
        }
        $this->assertDatabaseCount('catalog_audit_events', 0);
        $this->assertDatabaseCount('products', 0);
    }

    public static function invalidStates(): array
    {
        return [['disabled'], ['restricted'], ['idle'], ['revoked']];
    }

    #[DataProvider('invalidStates')]
    public function test_invalid_owner_is_denied_for_every_catalog_destination(string $state): void
    {
        foreach ($this->destinations() as [$method, $route, $parameters]) {
            $this->owner->forceFill(['status' => 'active'])->save();
            DB::table('management_users')->where('id', $this->owner->id)->update(['role' => 'owner']);
            $this->owner = $this->owner->fresh();
            $this->ownerLogin();
            match ($state) {
                'disabled' => $this->owner->forceFill(['status' => 'disabled'])->save(),
                'restricted' => DB::table('management_users')->where('id', $this->owner->id)->update(['role' => 'restricted']),
                'idle' => $this->withSession(['owner_last_activity' => now()->subHours(4)->timestamp]),
                'revoked' => $this->withSession(['owner_credential_version' => '999']),
            };
            $this->call($method, route($route, $parameters))->assertForbidden();
        }
        $this->assertDatabaseCount('catalog_audit_events', 0);
    }

    public function test_actual_csrf_middleware_rejects_each_write_without_token_and_accepts_a_valid_token(): void
    {
        $brand = $this->brand();
        $this->ownerLogin();
        $this->app->instance(PreventRequestForgery::class, new class($this->app, $this->app['encrypter']) extends PreventRequestForgery
        {
            protected function runningUnitTests(): bool
            {
                return false;
            }
        });
        foreach ($this->destinations() as [$method, $route, $parameters]) {
            if ($method !== 'GET') {
                $this->call($method, route($route, $parameters))->assertStatus(419);
            }
        }
        $token = Str::random(40);
        $this->withSession(['_token' => $token]);
        $this->post(route('owner.catalog.store', ['type' => 'products']), ['_token' => $token, 'brand_id' => $brand->id, 'name' => 'صحيح'])->assertRedirect();
        $this->assertDatabaseCount('products', 1);
    }

    public function test_real_forms_recycle_no_store_no_purge_and_unknown_attributes_are_not_accepted(): void
    {
        $product = $this->product();
        $this->ownerLogin();
        foreach (['products', 'brands', 'concerns'] as $type) {
            $this->get(route('owner.catalog.create', compact('type')))->assertOk()->assertHeader('X-Frame-Options', 'DENY')->assertHeader('Referrer-Policy', 'no-referrer')->assertSee('حفظ التغييرات');
            $this->get(route('owner.catalog.recycle', compact('type')))->assertOk()->assertSee('لا يوجد حذف نهائي');
        }
        $this->delete(route('owner.catalog.update', ['type' => 'products', 'id' => $product->id]))->assertStatus(405);
        $this->post('/api/v1/products', ['name' => 'bad'])->assertStatus(405);
        $this->get('/admin/login')->assertNotFound();
        $this->patch(route('owner.catalog.update', ['type' => 'products', 'id' => $product->id]), ['lock_version' => '1', 'deleted_at' => now()->toDateString(), 'owner_id' => $this->owner->id])->assertStatus(422);
        $this->assertDatabaseCount('catalog_audit_events', 0);
        $this->assertNotNull(Auth::guard('owner')->user());
        $this->assertSame(1, DB::table('products')->count());
    }
}
