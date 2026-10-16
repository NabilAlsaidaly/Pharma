<?php

namespace Tests\Feature\Gifts;

use App\Domains\Gifts\Application\ManageGiftOccasion;
use App\Domains\Gifts\Application\ManageGiftPackage;
use App\Domains\Identity\Domain\Enums\CustomerAccountStatus;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\GiftTestCase;

class GiftRequestTest extends GiftTestCase
{
    public function test_request_snapshot_is_durable_owned_private_and_replays_before_current_eligibility(): void
    {
        $product = $this->product(['price_usd' => null]);
        $id = $this->gift(['components' => [['type' => 'catalog', 'product_id' => $product->id, 'quantity' => 2, 'sort_order' => 0]]]);
        $customer = $this->customer();
        $this->actingAs($customer, 'customer');
        $payload = $this->giftInput($id, ['sender_display_name' => 'مرسل خاص', 'message' => 'رسالة خاصة']);
        $response = $this->postJson('/api/v1/gifts/requests', $payload)->assertCreated()->assertJsonPath('data.display_price_snapshot', '50.00')->assertJsonPath('data.is_confirmed_order', false)->assertDontSee('recipient_name')->assertDontSee('رسالة خاصة');
        $rid = $response->json('data.id');
        $original = DB::table('gift_requests')->where('id', $rid)->first();
        $product->update(['name' => 'اسم حالي مختلف', 'status' => 'hidden']);
        app(ManageGiftPackage::class)->execute($id, $this->owner, $this->giftCommand('3', 'unpublish'));
        $customer->update(['email' => 'changed@example.test']);
        $this->postJson('/api/v1/gifts/requests', $payload)->assertOk()->assertJsonPath('data.id', $rid);
        $this->postJson('/api/v1/gifts/requests', array_replace($payload, ['message' => 'مختلف']))->assertStatus(409);
        $this->getJson('/api/v1/gifts/requests')->assertOk()->assertJsonCount(1, 'data')->assertDontSee('account_email')->assertDontSee('recipient_phone')->assertDontSee('رسالة خاصة');
        $this->getJson('/api/v1/gifts/requests/'.$rid)->assertOk()->assertJsonPath('data.components.0.name', 'مرطب البشرة')->assertJsonPath('data.message', 'رسالة خاصة')->assertJsonPath('data.account_email', $original->account_email)->assertDontSee('payload_fingerprint')->assertDontSee('review_status');
        $this->assertEquals($original, DB::table('gift_requests')->where('id', $rid)->first());
        $this->assertDatabaseCount('gift_requests', 1);
        $this->assertDatabaseCount('gift_request_components', 1);
        foreach (['carts', 'checkout_intents', 'gift_request_reviews', 'gift_review_events'] as $table) {
            $this->assertDatabaseCount($table, 0);
        }
        $other = $customer->replicate();
        $other->google_sub = 'other-google';
        $other->email = 'other@example.test';
        $other->save();
        $this->actingAs($other, 'customer');
        $foreign = $this->getJson('/api/v1/gifts/requests/'.$rid)->assertNotFound()->json('error');
        $this->assertSame($foreign, $this->getJson('/api/v1/gifts/requests/'.Str::uuid7())->assertNotFound()->json('error'));
    }

    public function test_selected_currency_and_exact_published_version_are_checked_for_new_operations(): void
    {
        $id = $this->gift(['values' => ['name' => 'عملة واحدة', 'price_usd' => '0.00', 'price_syp' => '900.01', 'availability' => 'available']]);
        $this->actingAs($this->customer(), 'customer');
        $this->postJson('/api/v1/gifts/requests', $this->giftInput($id))->assertStatus(409);
        $this->postJson('/api/v1/gifts/requests', $this->giftInput($id, ['currency' => 'SYP']))->assertCreated()->assertJsonPath('data.display_price_snapshot', '900.01');
        $this->postJson('/api/v1/gifts/requests', $this->giftInput($id, ['currency' => 'SYP', 'expected_published_version' => '2']))->assertStatus(409);
    }

    public function test_occasion_snapshot_on_request_availability_and_archival_are_independent(): void
    {
        $oid = app(ManageGiftOccasion::class)->execute(null, $this->owner, $this->giftCommand(extra: ['values' => ['name' => 'مناسبة تجريبية', 'is_active' => '1']]));
        $product = $this->product(['availability_status' => 'on_request']);
        $id = $this->gift(['occasion_ids' => [$oid], 'components' => [['type' => 'catalog', 'product_id' => $product->id, 'quantity' => 1, 'sort_order' => 0]]]);
        $this->actingAs($this->customer(), 'customer');
        $result = $this->postJson('/api/v1/gifts/requests', $this->giftInput($id, ['occasion_id' => $oid]))->assertCreated()->assertJsonPath('data.requires_fulfillment_review', true);
        $rid = $result->json('data.id');
        app(ManageGiftOccasion::class)->execute($oid, $this->owner, $this->giftCommand('1', 'archive'));
        $slug = DB::table('gift_packages')->where('id', $id)->value('slug');
        $this->getJson('/api/v1/gifts/packages/'.$slug)->assertOk()->assertJsonCount(0, 'data.occasions');
        $this->postJson('/api/v1/gifts/requests', $this->giftInput($id, ['occasion_id' => $oid]))->assertStatus(409);
        $this->getJson('/api/v1/gifts/requests/'.$rid)->assertJsonPath('data.occasion_name', 'مناسبة تجريبية');
        $this->postJson('/api/v1/gifts/requests', $this->giftInput($id))->assertCreated();
        app(ManageGiftOccasion::class)->execute($oid, $this->owner, $this->giftCommand('2', 'restore'));
        $this->assertDatabaseHas('gift_occasions', ['id' => $oid, 'is_active' => 0, 'archived_at' => null]);
    }

    public static function invalidFields(): array
    {
        return array_map(fn ($extra) => [$extra], [
            ['quantity' => 2], ['components' => []], ['customer_id' => 'forged'], ['price' => '1.00'], ['payment_status' => 'paid'], ['shipping_fee' => '0.00'], ['owner_id' => 'forged'], ['review_status' => 'reviewed'],
            ['message' => '<script>alert(1)</script>'], ['message' => str_repeat('ع', 1001)], ['message' => ['bad']], ['sender_display_name' => '<img src=x onerror=alert(1)>'], ['currency' => 'EUR'], ['expected_published_version' => 3], ['expected_published_version' => '03'], ['expected_published_version' => '18446744073709551616'], ['recipient' => ['full_name' => 'اسم']], ['recipient' => 'invalid'], ['occasion_id' => 'invalid'], ['operation_id' => '00000000-0000-1000-8000-000000000000'],
        ]);
    }

    #[DataProvider('invalidFields')]
    public function test_strict_customer_payload_has_no_composition_or_financial_overrides(array $extra): void
    {
        $id = $this->gift();
        $this->actingAs($this->customer(), 'customer');
        $this->postJson('/api/v1/gifts/requests', $this->giftInput($id, $extra))->assertStatus(422)->assertHeader('Cache-Control', 'no-store, private')->assertDontSee('SQLSTATE');
        $this->assertDatabaseCount('gift_requests', 0);
    }

    public function test_guest_and_suspended_customer_cannot_submit_or_replay(): void
    {
        $this->withHeader('Origin', 'https://frontend.test');
        $id = $this->gift();
        $payload = $this->giftInput($id);
        $this->postJson('/api/v1/gifts/requests', $payload)->assertUnauthorized();
        $customer = $this->customer();
        $this->actingAs($customer, 'customer');
        $this->postJson('/api/v1/gifts/requests', $payload)->assertCreated();
        $customer->update(['account_status' => CustomerAccountStatus::Suspended]);
        $this->postJson('/api/v1/gifts/requests', $payload)->assertForbidden();
        $this->assertDatabaseCount('gift_requests', 1);
    }
}
