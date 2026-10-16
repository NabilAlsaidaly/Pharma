<?php

namespace Tests\Feature\RequestManagement;

use App\Domains\Cart\Application\CartOperations;
use App\Domains\Cart\Domain\Exceptions\CartConflict;
use App\Domains\Catalog\Application\Administration\ArchiveCatalogRecord;
use App\Domains\Catalog\Application\Administration\CatalogChange;
use App\Domains\Identity\Infrastructure\Persistence\Models\Customer;
use App\Domains\Orders\Application\Review\ReviewRequestAction;
use App\Domains\Orders\Application\SubmitPreliminaryCheckoutRequest;
use App\Domains\Orders\Infrastructure\Persistence\Models\PreliminaryRequestReview;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\OwnerDashboardTestCase;

class RequestReviewCompatibilityTest extends OwnerDashboardTestCase
{
    public function test_customer_retry_history_authorization_and_contact_contract_survive_review_and_catalog_archival(): void
    {
        $customer = $this->customer();
        $product = $this->product(['price_usd' => '12']);
        $cart = app(CartOperations::class)->add($customer->id, $product->id, null, 2);
        $input = ['operation_id' => (string) Str::uuid7(), 'currency' => 'USD', 'expected_cart_version' => '1', 'contact' => ['full_name' => 'Original customer', 'phone' => '+963999999999', 'governorate' => 'دمشق', 'city' => 'دمشق', 'address' => 'Original address', 'notes' => 'Customer original note']];
        $submit = app(SubmitPreliminaryCheckoutRequest::class);
        $saved = $submit->execute($customer->id, $input);
        $id = $saved['intent']->id;
        $history = [DB::table('checkout_intents')->get()->toJson(), DB::table('checkout_intent_items')->get()->toJson()];
        $this->actingAs($customer, 'customer')->withHeader('Origin', 'https://frontend.test');
        $before = $this->get('/api/v1/checkout/intents/'.$id, ['Accept' => 'application/json'])->assertOk()->json();
        app(ReviewRequestAction::class)->execute($id, $this->owner, (string) Str::uuid7(), '0', 'transition', 'under_review');
        app(ReviewRequestAction::class)->execute($id, $this->owner, (string) Str::uuid7(), '1', 'note', note: 'Private review sentinel');
        app(ReviewRequestAction::class)->execute($id, $this->owner, (string) Str::uuid7(), '2', 'transition', 'reviewed');
        $product->update(['status' => 'hidden']);
        app(ArchiveCatalogRecord::class)->execute('products', $product->id, '1', false, new CatalogChange($this->owner, 'fixture'));
        app(CartOperations::class)->remove($customer->id, $cart['item']['id']);
        $replay = $submit->execute($customer->id, $input);
        $this->assertSame($id, $replay['intent']->id);
        $this->assertFalse($replay['created']);
        try {
            $submit->execute($customer->id, array_replace($input, ['currency' => 'SYP']));
            $this->fail('Changed original Customer operation must conflict.');
        } catch (CartConflict $exception) {
            $this->assertSame('operation_payload_conflict', $exception->reason);
        }
        $after = $this->get('/api/v1/checkout/intents/'.$id, ['Accept' => 'application/json'])->assertOk()->assertDontSee('Private review sentinel')->assertJsonPath('data.status', 'submitted')->json();
        $this->assertSame($before['data'], $after['data']);
        $this->get('/api/v1/checkout/intents', ['Accept' => 'application/json'])->assertOk()->assertDontSee('Private review sentinel')->assertJsonPath('data.0.id', $id);
        $other = Customer::query()->create(['name' => 'Other', 'email' => 'other@example.test', 'google_sub' => 'other-sub']);
        $this->actingAs($other, 'customer');
        $this->get('/api/v1/checkout/intents/'.$id, ['Accept' => 'application/json'])->assertNotFound();
        $this->assertSame($history, [DB::table('checkout_intents')->get()->toJson(), DB::table('checkout_intent_items')->get()->toJson()]);
        $this->ownerLogin();
        $this->get(route('owner.requests.show', ['id' => $id]))->assertOk()->assertSee($product->id)->assertSee('Customer original note')->assertSee('Private review sentinel');
    }

    public function test_actual_schema_uniques_restrictive_foreign_keys_and_submission_enum_are_preserved(): void
    {
        $id = $this->requestFixture();
        $review = PreliminaryRequestReview::query()->create(['checkout_intent_id' => $id, 'review_status' => 'new']);
        $this->assertConstraint(fn () => PreliminaryRequestReview::query()->create(['checkout_intent_id' => $id, 'review_status' => 'new']), 1062);
        $this->assertConstraint(fn () => DB::table('checkout_intents')->where('id', $id)->delete(), 1451);
        $this->assertConstraint(fn () => DB::table('preliminary_request_reviews')->where('id', $review->id)->update(['review_status' => 'paid']), 1265);
        $column = DB::selectOne("SELECT COLUMN_TYPE FROM information_schema.columns WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='checkout_intents' AND COLUMN_NAME='status'");
        $this->assertSame("enum('submitted')", $column->COLUMN_TYPE);
        foreach (['price', 'amount', 'deposit', 'phone', 'email', 'address'] as $forbidden) {
            $columns = DB::select("SELECT COLUMN_NAME FROM information_schema.columns WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME IN ('preliminary_request_reviews','preliminary_request_review_events') AND COLUMN_NAME LIKE ?", ['%'.$forbidden.'%']);
            $this->assertSame([], $columns);
        }
    }

    public function test_filters_inferred_new_and_metrics_distinguish_submission_and_activity_time(): void
    {
        $this->travelTo(CarbonImmutable::parse('2026-10-16 12:00:00', 'UTC'));
        $one = $this->requestFixture();
        $two = $this->requestFixture();
        DB::table('checkout_intents')->where('id', $one)->update(['created_at' => '2026-10-01 12:00:00']);
        DB::table('checkout_intent_items')->where('checkout_intent_id', $one)->update(['requires_fulfillment_review' => 1]);
        app(ReviewRequestAction::class)->execute($one, $this->owner, (string) Str::uuid7(), '0', 'transition', 'under_review');
        app(ReviewRequestAction::class)->execute($one, $this->owner, (string) Str::uuid7(), '1', 'note', note: 'نشاط اليوم لطلب قديم');
        $this->ownerLogin();
        $this->assertSame($two, $this->get(route('owner.requests.index', ['review_status' => 'new']))->assertOk()->viewData('records')->sole()->id);
        $filters = ['review_status' => 'under_review', 'fulfillment' => '1', 'reviewed' => '0', 'activity_from' => '2026-10-16', 'activity_to' => '2026-10-16', 'per_page' => 10];
        $this->assertSame($one, $this->get(route('owner.requests.index', $filters))->assertOk()->viewData('records')->sole()->id);
        $this->get(route('owner.requests.index', ['fulfillment' => '0']))->assertOk()->assertDontSee(route('owner.requests.show', ['id' => $one]), false);
        $response = $this->get(route('owner.dashboard', ['period' => 'today']))->assertOk();
        $this->assertSame(1, $response->viewData('metrics')['range_requests']);
        $review = $response->viewData('reviewMetrics');
        $this->assertSame(1, (int) $review['counts']['new_count']);
        $this->assertSame(1, (int) $review['counts']['under_review']);
        $this->assertSame(2, (int) $review['activity']['events']);
        $this->assertSame(1, (int) $review['activity']['requests']);
        foreach ([['activity_from' => '2026-10-01'], ['activity_from' => '2026-10-15', 'activity_to' => '2026-10-01'], ['review_status' => 'paid'], ['email' => 'private@example.test'], ['page' => 1001]] as $invalid) {
            $this->get(route('owner.requests.index', $invalid))->assertUnprocessable();
        }
    }
}
