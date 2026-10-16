<?php

namespace Tests\Feature\StoreConfiguration;

use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\StoreConfigurationTestCase;

class StoreSettingsTest extends StoreConfigurationTestCase
{
    public function test_empty_schema_is_available_without_defaults_and_gets_never_write(): void
    {
        $this->ownerLogin();
        $this->get(route('owner.settings.index'))->assertOk()->assertSee('إعدادات المتجر')->assertSee('إيقاف استقبال')->assertSee('الطلبات مؤجل');
        foreach (['profile', 'currency', 'payment', 'zones'] as $group) {
            $this->get(route('owner.settings.group', ['group' => $group]))->assertOk();
        }
        $this->getJson('/api/v1/storefront/configuration')->assertOk()->assertJsonPath('data.store', null)->assertJsonPath('data.currency_presentation', null);
        $this->assertDatabaseCount('store_change_events', 0);
        $this->assertDatabaseCount('store_profiles', 0);
    }

    public function test_profile_publication_preserves_published_snapshot_until_explicit_new_publication(): void
    {
        $this->ownerLogin();
        $id = $this->setting(values: ['name' => 'متجر أول', 'email' => 'public@example.test', 'phone' => '+963 111111111', 'facebook' => 'https://www.facebook.com/store-fixture']);
        $this->getJson('/api/v1/storefront/configuration')->assertJsonPath('data.store', null);
        $this->setting(values: [], version: '1', action: 'publish');
        $this->getJson('/api/v1/storefront/configuration')->assertOk()->assertHeader('Cache-Control', 'no-store, private')->assertJsonPath('data.store.name', 'متجر أول')->assertJsonPath('data.store.social_links.0.platform', 'facebook')->assertDontSee('owner_id');
        $this->setting(values: ['name' => 'مسودة خاصة', 'email' => null, 'facebook' => ''], version: '2');
        $this->getJson('/api/v1/storefront/configuration')->assertJsonPath('data.store.name', 'متجر أول');
        $this->get(route('owner.settings.group', ['group' => 'profile', 'revision' => '2']))->assertOk()->assertSee('متجر أول')->assertSee('مسودة خاصة');
        $this->setting(values: [], version: '3', action: 'publish');
        $this->getJson('/api/v1/storefront/configuration')->assertJsonPath('data.store.name', 'مسودة خاصة')->assertJsonPath('data.store.email', null)->assertJsonCount(0, 'data.store.social_links');
        $this->assertDatabaseCount('store_profiles_revisions', 4);
        $this->assertDatabaseCount('store_change_events', 4);
        $this->assertDatabaseHas('store_profiles_revisions', ['record_id' => $id, 'version' => 2, 'name' => 'متجر أول', 'email' => 'public@example.test']);
    }

    public function test_operation_replay_precedes_stale_and_changed_payload_conflicts_without_audit(): void
    {
        $this->ownerLogin();
        $payload = $this->command(['name' => 'اسم تجريبي']);
        $url = route('owner.settings.mutate', ['group' => 'profile']);
        $this->post($url, $payload)->assertRedirect();
        $this->post($url, $payload)->assertRedirect();
        $payload['values']['name'] = 'تغيير غير مطابق';
        $this->post($url, $payload)->assertStatus(409);
        $this->post($url, $this->command(['name' => 'قديم']))->assertStatus(409);
        $this->post(route('owner.settings.mutate', ['group' => 'profile', 'id' => (string) Str::uuid7()]), $this->command(['name' => 'هدف مختلف'], '1'))->assertNotFound();
        $this->assertDatabaseCount('store_profiles', 1);
        $this->assertDatabaseCount('store_profiles_revisions', 1);
        $this->assertDatabaseCount('store_change_events', 1);
    }

    public static function invalidProfileFields(): array
    {
        return array_map(fn ($value) => [$value], [
            ['email' => 'bad'], ['phone' => 'call someone'], ['facebook' => 'javascript:alert(1)'], ['instagram' => 'https://evil.test/user'],
            ['youtube' => 'https://user:secret@youtube.com/path'], ['whatsapp' => 'https://wa.me/%0a123'], ['name' => '<script>alert(1)</script>'],
            ['name' => '&#x3c;script>'], ['about' => str_repeat('x', 6001)], ['APP_KEY' => 'secret'], ['database_password' => 'secret'], ['name' => ['nested']],
        ]);
    }

    #[DataProvider('invalidProfileFields')]
    public function test_strict_profile_validation_and_no_arbitrary_keys(array $values): void
    {
        $this->ownerLogin();
        $this->post(route('owner.settings.mutate', ['group' => 'profile']), $this->command(array_replace(['name' => 'سليم'], $values)))->assertStatus(422)->assertDontSee('secret');
        $this->assertDatabaseCount('store_change_events', 0);
    }

    public function test_currency_preferences_are_presentation_only_and_no_currency_disable_field(): void
    {
        $request = $this->requestFixture();
        $facts = [DB::table('products')->get()->toJson(), DB::table('carts')->get()->toJson(), DB::table('checkout_intents')->get()->toJson()];
        $this->setting('currency', ['first_currency' => 'SYP', 'usd_label' => 'دولار أمريكي', 'syp_label' => 'ليرة سورية', 'usd_symbol' => '$']);
        $this->setting('currency', [], '1', action: 'publish');
        $this->getJson('/api/v1/storefront/configuration')->assertJsonPath('data.currency_presentation.first_currency', 'SYP');
        $this->ownerLogin();
        $this->post(route('owner.settings.mutate', ['group' => 'currency']), $this->command(['usd_enabled' => '0'], '2'))->assertStatus(422);
        $this->assertSame($facts, [DB::table('products')->get()->toJson(), DB::table('carts')->get()->toJson(), DB::table('checkout_intents')->get()->toJson()]);
        $this->assertDatabaseHas('checkout_intents', ['id' => $request, 'status' => 'submitted']);
    }

    public function test_database_constraints_and_sql_audit_failure_roll_back_business_revision(): void
    {
        $armed = true;
        DB::listen(function ($query) use (&$armed): void {
            if ($armed && str_starts_with($query->sql, 'insert into `store_profiles_revisions`')) {
                $armed = false;
                DB::table('store_profiles_revisions')->insert(['id' => (string) Str::uuid7(), 'record_id' => (string) Str::uuid7(), 'version' => 1, 'owner_id' => $this->owner->id, 'action' => 'save', 'name' => 'constraint fixture', 'created_at' => now()]);
            }
        });
        try {
            $this->setting();
            $this->fail('Actual restrictive foreign key must reject the injected SQL.');
        } catch (QueryException $exception) {
            $this->assertSame(1452, $exception->errorInfo[1]);
        }
        $this->assertDatabaseCount('store_profiles', 0);
        $this->assertDatabaseCount('store_profiles_revisions', 0);
        $this->assertDatabaseCount('store_change_events', 0);
    }

    public function test_missing_schema_safe_unavailable_without_invented_values(): void
    {
        Schema::shouldReceive('hasTable')->with('store_profiles')->andReturn(false);
        $this->getJson('/api/v1/storefront/configuration')->assertStatus(503)->assertJsonPath('error.code', 'SERVICE_UNAVAILABLE');
    }

    public function test_unsigned_versions_above_javascript_precision_remain_exact_and_overflow_is_rejected(): void
    {
        $this->setting();
        DB::table('store_profiles')->update(['lock_version' => '9007199254740993']);
        $this->setting(values: ['description' => 'إصدار كبير صحيح'], version: '9007199254740993');
        $this->setting(values: [], version: '9007199254740994', action: 'publish');
        $this->getJson('/api/v1/storefront/configuration')->assertOk()->assertJsonPath('data.store.revision', '9007199254740995');
        $this->assertDatabaseHas('store_change_events', ['previous_version' => '9007199254740994', 'new_version' => '9007199254740995']);
        $this->ownerLogin();
        $this->post(route('owner.settings.mutate', ['group' => 'profile']), $this->command(['name' => 'رفض التجاوز'], '18446744073709551615'))->assertStatus(422);
        $this->assertDatabaseHas('store_profiles', ['lock_version' => '9007199254740995']);
        $this->assertDatabaseCount('store_change_events', 3);
    }
}
