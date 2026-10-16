<?php

namespace Tests\Feature\OwnerDashboard;

use App\Domains\ManagementDashboard\Application\OwnerNavigation;
use App\Domains\ManagementIdentity\Interfaces\Http\Middleware\RequireOwner;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;
use Tests\OwnerDashboardTestCase;

class OwnerDashboardSecurityTest extends OwnerDashboardTestCase
{
    private function destinations(): array
    {
        $paths = [route('owner.dashboard'), route('owner.search')];
        foreach (['products', 'brands', 'concerns', 'requests'] as $resource) {
            $paths[] = route('owner.'.$resource.'.index');
            $paths[] = route('owner.'.$resource.'.show', ['id' => (string) Str::uuid7()]);
        }

        return $paths;
    }

    public function test_every_read_route_denies_guests_and_customers_and_requires_the_existing_owner_middleware(): void
    {
        foreach ($this->destinations() as $url) {
            $this->get($url)->assertRedirect(route('owner.login'));
        }
        $this->actingAs($this->customer(), 'customer');
        foreach ($this->destinations() as $url) {
            $this->get($url)->assertRedirect(route('owner.login'));
        }
        foreach (collect(Route::getRoutes())->filter(fn ($r) => str_starts_with((string) $r->getName(), 'owner.') && ! str_contains($r->getName(), 'login')) as $route) {
            $this->assertContains(RequireOwner::class, $route->gatherMiddleware());
        }
        $this->get('/admin/login')->assertNotFound();
        $this->get('/login')->assertNotFound();
    }

    public function test_state_idle_version_and_restricted_identity_revoke_every_new_destination(): void
    {
        foreach (['disabled', 'idle', 'version', 'restricted'] as $state) {
            foreach ($this->destinations() as $url) {
                $this->owner->forceFill(['status' => 'active'])->save();
                DB::table('management_users')->where('id', $this->owner->id)->update(['role' => 'owner']);
                $this->owner = $this->owner->fresh();
                $this->actingAs($this->owner, 'owner')->withSession(['owner_credential_version' => '1', 'owner_last_activity' => now()->timestamp]);
                if ($state === 'disabled') {
                    $this->owner->forceFill(['status' => 'disabled'])->save();
                } elseif ($state === 'idle') {
                    $this->withSession(['owner_last_activity' => now()->subMinutes(30)->timestamp]);
                } elseif ($state === 'version') {
                    $this->withSession(['owner_credential_version' => '0']);
                } else {
                    DB::table('management_users')->where('id', $this->owner->id)->update(['role' => 'restricted']);
                }
                $this->get($url)->assertForbidden()->assertHeader('Cache-Control', 'max-age=0, no-store, private');
                $this->assertGuest('owner');
            }
        }
    }

    public function test_navigation_registry_has_real_destinations_active_state_and_denies_invalid_abilities(): void
    {
        $this->ownerLogin();
        $response = $this->get(route('owner.products.index'))->assertOk()->assertSee('aria-current="page"', false);
        $navigation = app(OwnerNavigation::class);
        $entries = $navigation->resolve($navigation->definitions(), $this->owner);
        $this->assertCount(12, $entries);
        foreach ($entries as $entry) {
            $this->assertTrue(Route::has($entry['route']));
        }
        $bad = [['key' => 'bad', 'route' => 'owner.imaginary', 'order' => 1, 'ability' => null], ['key' => 'denied', 'route' => 'owner.products.index', 'order' => 2, 'ability' => 'unimplemented-staff-ability']];
        $this->assertSame([], $navigation->resolve($bad, $this->owner));
        $this->assertSame([], $navigation->resolve($navigation->definitions(), null));
        $this->assertSame(['products'], array_column(array_filter($entries, fn ($item) => $item['active']), 'key'));
    }

    public function test_dashboard_reads_do_not_create_business_writes_or_expose_mutations(): void
    {
        $id = $this->requestFixture();
        $this->ownerLogin();
        $tables = ['products', 'brands', 'concerns', 'customers', 'carts', 'cart_items', 'checkout_intents', 'checkout_intent_items'];
        $before = array_map(fn ($table) => DB::table($table)->orderBy('id')->get()->toArray(), $tables);
        foreach (['owner.dashboard', 'owner.products.index', 'owner.brands.index', 'owner.concerns.index', 'owner.requests.index'] as $name) {
            $this->get(route($name))->assertOk()->assertHeader('Cache-Control', 'max-age=0, no-store, private');
            $this->post(route($name), ['status' => 'confirmed'])->assertStatus(405)->assertDontSee('Stack trace');
        }
        $this->get(route('owner.requests.show', ['id' => $id]))->assertOk();
        $this->patch(route('owner.requests.show', ['id' => $id]), ['status' => 'confirmed'])->assertStatus(405);
        $this->delete(route('owner.products.show', ['id' => DB::table('products')->first()->id]))->assertStatus(405);
        $this->assertEquals($before, array_map(fn ($table) => DB::table($table)->orderBy('id')->get()->toArray(), $tables));
        $this->assertGuest('customer');
        $this->getJson('/api/v1/auth/me')->assertUnauthorized();
    }
}
