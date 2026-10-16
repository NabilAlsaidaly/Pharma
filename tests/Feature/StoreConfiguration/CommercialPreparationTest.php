<?php

namespace Tests\Feature\StoreConfiguration;

use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\StoreConfigurationTestCase;

class CommercialPreparationTest extends StoreConfigurationTestCase
{
    public static function invalidPayments(): array
    {
        return [['full', '99.99'], ['full', '0'], ['deposit', '0'], ['deposit', '100'], ['deposit', '-1'], ['deposit', '0.001'], ['deposit', '1e1'], ['deposit', 'NaN'], ['cod', '20'], ['deposit', 20.5]];
    }

    #[DataProvider('invalidPayments')]
    public function test_invalid_or_unapproved_payment_models_are_rejected(string $mode, mixed $percentage): void
    {
        $this->ownerLogin();
        $this->post(route('owner.settings.mutate', ['group' => 'payment']), $this->command(compact('mode', 'percentage')))->assertStatus(422);
        $this->assertDatabaseCount('store_payment_policies', 0);
        $this->assertDatabaseCount('store_change_events', 0);
    }

    public function test_boundary_percentages_and_immutable_revisions_have_no_checkout_or_cart_effect(): void
    {
        $id = $this->requestFixture();
        $tables = ['checkout_intents', 'checkout_intent_items', 'carts', 'cart_items', 'products', 'product_variants', 'preliminary_request_reviews', 'preliminary_request_review_events'];
        $before = array_map(fn ($t) => DB::table($t)->orderBy('id')->get()->toJson(), $tables);
        $this->setting('payment', ['mode' => 'full', 'percentage' => '100']);
        $this->setting('payment', ['mode' => 'deposit', 'percentage' => '0.01'], '1');
        $this->setting('payment', ['percentage' => '99.99'], '2');
        $this->assertSame(['100.00', '0.01', '99.99'], DB::table('store_payment_policies_revisions')->orderBy('version')->pluck('percentage')->all());
        $this->ownerLogin();
        $this->post(route('owner.settings.mutate', ['group' => 'payment']), $this->command([], '3', 'publish'))->assertStatus(422);
        $this->getJson('/api/v1/storefront/configuration')->assertOk()->assertDontSee('percentage')->assertDontSee('deposit');
        $this->assertSame($before, array_map(fn ($t) => DB::table($t)->orderBy('id')->get()->toJson(), $tables));
        $this->assertDatabaseHas('checkout_intents', ['id' => $id, 'status' => 'submitted']);
        foreach (['orders', 'payments', 'shipments', 'payment_proofs', 'inventory_reservations'] as $table) {
            $this->assertFalse(Schema::hasTable($table));
        }
    }

    public function test_zone_null_zero_independent_fees_duplicate_archive_restore_and_revision_history(): void
    {
        $this->ownerLogin();
        $url = route('owner.settings.mutate', ['group' => 'zones']);
        $values = ['name' => 'منطقة اختبار', 'governorate' => 'دمشق', 'city' => '  منطقة   تجريبية ', 'fee_usd' => null, 'fee_syp' => '0', 'sort_order' => '0'];
        $this->post($url, $this->command($values))->assertRedirect();
        $zone = DB::table('store_delivery_zones')->sole();
        $this->assertNull($zone->fee_usd);
        $this->assertSame('0.00', $zone->fee_syp);
        $values['city'] = 'منطقة تجريبية';
        $this->post($url, $this->command($values))->assertStatus(409);
        $edit = route('owner.settings.mutate', ['group' => 'zones', 'id' => $zone->id]);
        $this->post($edit, $this->command(['fee_usd' => '9999999999999999.99'], '1'))->assertRedirect();
        $this->post($edit, $this->command([], '2', 'archive'))->assertRedirect();
        $this->post($url, $this->command($values))->assertStatus(409);
        $this->post($edit, $this->command([], '3', 'restore'))->assertRedirect();
        $this->assertDatabaseHas('store_delivery_zones', ['id' => $zone->id, 'lock_version' => 4, 'fee_usd' => '9999999999999999.99', 'fee_syp' => '0.00', 'published_version' => null, 'archived_at' => null]);
        $this->assertDatabaseCount('store_delivery_zones_revisions', 4);
        $this->getJson('/api/v1/storefront/configuration')->assertDontSee('fee_usd')->assertDontSee('منطقة تجريبية');
        $this->get(route('owner.settings.edit', ['group' => 'zones', 'id' => $zone->id]))->assertOk()->assertSee('بانتظار')->assertSee('الصفر مجاني');
    }

    public static function invalidFees(): array
    {
        return [['-0.01'], ['1.001'], ['1e2'], ['10000000000000000.00'], [1.5], [['value' => '2']], ['free']];
    }

    #[DataProvider('invalidFees')]
    public function test_delivery_fee_bounds_are_strict(mixed $fee): void
    {
        $this->ownerLogin();
        $this->post(route('owner.settings.mutate', ['group' => 'zones']), $this->command(['name' => 'اختبار', 'governorate' => 'دمشق', 'sort_order' => '0', 'fee_usd' => $fee]))->assertStatus(422);
        $this->assertDatabaseCount('store_delivery_zones', 0);
    }

    public function test_real_database_checks_prevent_negative_fees_and_invalid_deposits(): void
    {
        $this->setting('payment', ['mode' => 'deposit', 'percentage' => '20.00']);
        try {
            DB::table('store_payment_policies')->update(['percentage' => '100.00']);
            $this->fail('MySQL CHECK must reject a100%deposit.');
        } catch (QueryException $exception) {
            $this->assertSame(3819, $exception->errorInfo[1]);
        }
        $this->setting('zones', ['name' => 'اختبار', 'governorate' => 'دمشق', 'sort_order' => '0']);
        try {
            DB::table('store_delivery_zones')->update(['fee_syp' => '-1.00']);
            $this->fail('MySQL CHECK must reject negative delivery fees.');
        } catch (QueryException $exception) {
            $this->assertSame(3819, $exception->errorInfo[1]);
        }
    }
}
