<?php

namespace Tests\Feature\Delivery;

use App\Domains\Delivery\Application\ManageDeliveryWorker;
use App\Domains\Identity\Infrastructure\Persistence\Models\Customer;
use App\Domains\Settings\Application\ManageStoreSettings;
use App\Domains\Settings\Infrastructure\PublishedDeliveryZones;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Tests\GiftTestCase;

class DeliveryCompatibilityTest extends GiftTestCase
{
    public function test_checkout_and_gift_replays_snapshots_reviews_and_financial_boundaries_survive_delivery_changes(): void
    {
        $this->freezeTime();
        $cid = $this->requestFixture();
        $checkout = DB::table('checkout_intents')->where('id', $cid)->first();
        $customer = Customer::query()->sole();
        $this->actingAs($customer, 'customer');
        $checkoutInput = ['operation_id' => $checkout->operation_id, 'currency' => 'USD', 'expected_cart_version' => '1', 'contact' => array_intersect_key((array) $checkout, array_flip(['full_name', 'phone', 'governorate', 'city', 'address', 'notes']))];
        $gift = $this->gift();
        $giftInput = $this->giftInput($gift);
        $gid = $this->postJson('/api/v1/gifts/requests', $giftInput)->assertCreated()->json('data.id');
        $paths = ['/api/v1/checkout/intents/'.$cid, '/api/v1/gifts/requests/'.$gid, '/api/v1/cart', '/api/v1/checkout/intents', '/api/v1/gifts/requests'];
        $before = [];
        foreach ($paths as $path) {
            $response = $this->get($path, ['Accept' => 'application/json']);
            $this->assertSame(200, $response->status(), $path.' '.$response->getContent());
            $before[$path] = $response->json();
        }
        $tables = ['checkout_intents', 'checkout_intent_items', 'gift_requests', 'gift_request_components', 'cart_items', 'customers'];
        $facts = array_map(fn ($table) => DB::table($table)->get()->toJson(), $tables);
        $settings = app(ManageStoreSettings::class);
        $zone = $settings->execute('zones', null, $this->owner, $this->giftCommand(extra: ['values' => ['name' => 'معلومات فقط', 'governorate' => 'محافظة مختلفة', 'sort_order' => '0', 'fee_usd' => '100.00', 'fee_syp' => null]]));
        $settings->execute('zones', $zone, $this->owner, $this->giftCommand('1', 'publish'));
        $settings->execute('zones', $zone, $this->owner, $this->giftCommand('2', extra: ['values' => ['fee_usd' => '0.00']]));
        $settings->execute('zones', $zone, $this->owner, $this->giftCommand('3', 'publish'));
        $settings->execute('zones', $zone, $this->owner, $this->giftCommand('4', 'archive'));
        app(ManageDeliveryWorker::class)->execute(null, $this->owner, $this->giftCommand(extra: ['values' => ['name' => 'عامل خاص', 'phone' => '123456', 'is_active' => true]]));
        // A completely unavailable delivery read service is not a submission/replay dependency.
        $this->app->bind(PublishedDeliveryZones::class, fn () => throw new \RuntimeException('Unavailable delivery configuration'));
        $this->postJson('/api/v1/checkout/intents', $checkoutInput)->assertOk()->assertJsonPath('data.id', $cid);
        $this->postJson('/api/v1/gifts/requests', $giftInput)->assertOk()->assertJsonPath('data.id', $gid);
        foreach ($paths as $path) {
            $this->assertSame($before[$path], $this->get($path, ['Accept' => 'application/json'])->assertOk()->json());
        }
        $this->assertSame($facts, array_map(fn ($table) => DB::table($table)->get()->toJson(), $tables));
        $checkoutInput['contact']['city'] = 'مدينة أخرى';
        $giftInput['recipient']['city'] = 'مدينة أخرى';
        $this->postJson('/api/v1/checkout/intents', $checkoutInput)->assertStatus(409);
        $this->postJson('/api/v1/gifts/requests', $giftInput)->assertStatus(409);
        $this->ownerLogin();
        $this->get(route('owner.requests.show', ['id' => $cid]))->assertOk()->assertSee('Private-address-sentinel');
        $this->get(route('owner.gifts.requests.show', ['id' => $gid]))->assertOk()->assertSee('عنوان خاص للاختبار');
        foreach (['orders', 'payments', 'shipments', 'inventory_reservations'] as $table) {
            $this->assertFalse(Schema::hasTable($table));
        }
    }

    public function test_unknown_free_text_geography_still_accepts_new_preliminary_submissions_without_delivery_reader(): void
    {
        $cid = $this->requestFixture();
        $checkout = DB::table('checkout_intents')->where('id', $cid)->first();
        $customer = Customer::query()->sole();
        $this->actingAs($customer, 'customer');
        $gift = $this->gift();
        $this->app->bind(PublishedDeliveryZones::class, fn () => throw new \RuntimeException('Unavailable delivery configuration'));
        $input = ['operation_id' => (string) Str::uuid7(), 'currency' => 'USD', 'expected_cart_version' => '1', 'contact' => array_intersect_key((array) $checkout, array_flip(['full_name', 'phone', 'governorate', 'city', 'address', 'notes']))];
        $input['contact']['governorate'] = 'محافظة غير مهيأة';
        $this->postJson('/api/v1/checkout/intents', $input)->assertCreated()->assertJsonPath('data.is_confirmed_order', false);
        $this->postJson('/api/v1/gifts/requests', $this->giftInput($gift, ['recipient' => ['full_name' => 'مستلم', 'phone' => '123456', 'governorate' => 'محافظة غير مهيأة', 'city' => 'مدينة أخرى', 'address' => 'عنوان مستقل']]))->assertCreated()->assertJsonPath('data.delivery_scheduled', false);
        $input['contact']['zone_id'] = 'forged';
        $this->postJson('/api/v1/checkout/intents', $input)->assertUnprocessable();
    }
}
