<?php

namespace Tests\Feature\StoreConfiguration;

use App\Domains\Identity\Infrastructure\Persistence\Models\Customer;
use App\Domains\Settings\Application\ManageStoreSettings;
use Illuminate\Database\QueryException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Tests\StoreConfigurationTestCase;

class StoreCompatibilityTest extends StoreConfigurationTestCase
{
    public function test_real_customer_api_resources_and_historical_data_are_unchanged_by_business_configuration(): void
    {
        $this->freezeTime();
        $id = $this->requestFixture();
        $customer = Customer::query()->sole();
        $this->actingAs($customer, 'customer');
        $paths = ['/api/v1/auth/me', '/api/v1/products', '/api/v1/brands', '/api/v1/concerns', '/api/v1/cart', '/api/v1/favorites', '/api/v1/checkout/intents', '/api/v1/checkout/intents/'.$id];
        $before = [];
        foreach ($paths as $path) {
            $before[$path] = $this->get($path, ['Accept' => 'application/json'])->assertOk()->json();
        }
        $facts = [DB::table('checkout_intents')->get()->toJson(), DB::table('checkout_intent_items')->get()->toJson(), DB::table('cart_items')->get()->toJson()];
        $this->setting();
        $this->setting(values: [], version: '1', action: 'publish');
        $this->setting('payment', ['mode' => 'deposit', 'percentage' => '25.50']);
        $this->setting('zones', ['name' => 'تجهيز فقط', 'governorate' => 'دمشق', 'fee_usd' => '0.00', 'fee_syp' => null, 'sort_order' => '0']);
        $page = $this->content();
        $this->content(values: [], version: '1', id: $page, action: 'publish');
        foreach ($paths as $path) {
            $this->assertSame($before[$path], $this->get($path, ['Accept' => 'application/json'])->assertOk()->json(), $path);
        }
        $this->assertSame($facts, [DB::table('checkout_intents')->get()->toJson(), DB::table('checkout_intent_items')->get()->toJson(), DB::table('cart_items')->get()->toJson()]);
        $this->assertSame('submitted', DB::table('checkout_intents')->sole()->status);
    }

    public function test_stable_reserved_slugs_prevent_two_current_public_documents_at_one_url(): void
    {
        $this->ownerLogin();
        $id = $this->content();
        $this->content(values: [], version: '1', id: $id, action: 'publish');
        $url = route('owner.content.mutate', ['type' => 'pages', 'id' => $id]);
        $this->post($url, $this->command(['slug' => 'new-url'], '2'))->assertStatus(409);
        $this->post(route('owner.content.mutate', ['type' => 'pages']), $this->command(['title' => 'آخر', 'slug' => 'about-fixture', 'body' => 'نص']))->assertStatus(409);
        $this->getJson('/api/v1/storefront/pages/about-fixture')->assertOk()->assertJsonPath('data.id', $id);
        $this->assertDatabaseCount('content_pages_revisions', 2);
    }

    public function test_fresh_media_is_compensated_on_actual_sql_failure_and_old_public_image_survives(): void
    {
        Storage::fake('local');
        $id = $this->setting();
        app(ManageStoreSettings::class)->execute('profile', $id, $this->owner, $this->command([], '1', 'media'), UploadedFile::fake()->image('first.png'));
        $old = DB::table('store_profiles')->sole()->image_path;
        $this->setting(values: [], version: '2', action: 'publish');
        $armed = true;
        DB::listen(function ($query) use (&$armed): void {
            if ($armed && str_starts_with($query->sql, 'insert into `store_profiles_revisions`')) {
                $armed = false;
                DB::table('store_change_events')->insert(['request_id' => 'failing-media-audit']);
            }
        });
        try {
            app(ManageStoreSettings::class)->execute('profile', $id, $this->owner, $this->command([], '3', 'media'), UploadedFile::fake()->image('replacement.png', 30, 40));
            $this->fail('Actual SQL failure must roll back and compensate new bytes.');
        } catch (QueryException $exception) {
            $this->assertNotEmpty($exception->errorInfo);
        }
        $this->assertCount(1, Storage::disk('local')->allFiles('storefront'));
        Storage::disk('local')->assertExists($old);
        $this->assertDatabaseHas('store_profiles', ['id' => $id, 'lock_version' => 3, 'published_version' => 3, 'image_path' => $old]);
        $this->assertDatabaseCount('store_profiles_revisions', 3);
    }

    public function test_oversized_raster_fake_mime_double_extension_and_array_file_are_rejected(): void
    {
        Storage::fake('local');
        $this->setting();
        $this->ownerLogin();
        $url = route('owner.settings.mutate', ['group' => 'profile']);
        foreach ([UploadedFile::fake()->image('huge.png', 4097, 1), UploadedFile::fake()->image('pixels.png', 2200, 2200), UploadedFile::fake()->image('large.jpg')->size(5121), UploadedFile::fake()->image('file.php.png'), UploadedFile::fake()->createWithContent('pretend.jpg', 'not an image')] as $file) {
            $this->post($url, $this->command([], '1', 'media') + ['image' => $file])->assertStatus(422);
        }
        $this->post($url, $this->command([], '1', 'media') + ['image' => ['unexpected']])->assertStatus(422);
        $this->assertCount(0, Storage::disk('local')->allFiles('storefront'));
        $this->assertDatabaseCount('store_profiles_revisions', 1);
    }
}
