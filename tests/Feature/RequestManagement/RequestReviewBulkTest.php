<?php

namespace Tests\Feature\RequestManagement;

use App\Domains\Orders\Application\Review\ReviewRequestAction;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\OwnerDashboardTestCase;

class RequestReviewBulkTest extends OwnerDashboardTestCase
{
    private function requests(int $count): array
    {
        $id = $this->requestFixture();
        $ids = [$id];
        $base = (array) DB::table('checkout_intents')->where('id', $id)->first();
        $item = (array) DB::table('checkout_intent_items')->where('checkout_intent_id', $id)->first();
        unset($item['selection_key']);
        for ($i = 1; $i < $count; $i++) {
            $next = (string) Str::uuid7();
            DB::table('checkout_intents')->insert(array_replace($base, ['id' => $next, 'operation_id' => (string) Str::uuid7()]));
            DB::table('checkout_intent_items')->insert(array_replace($item, ['id' => (string) Str::uuid7(), 'checkout_intent_id' => $next]));
            $ids[] = $next;
        }

        return $ids;
    }

    private function preview(array $input = [])
    {
        return $this->post(route('owner.requests.preview'), array_replace(['scope' => 'matching', 'target' => 'under_review', 'filters' => ['review_status' => 'new']], $input));
    }

    private function consent(): array
    {
        return ['token' => session('request_review_confirmation.token'), 'confirm' => '1'];
    }

    public function test_matching_snapshot_across_pages_atomic_execution_and_committed_confirmation_replay(): void
    {
        $ids = $this->requests(28);
        $history = [DB::table('checkout_intents')->get()->toJson(), DB::table('checkout_intent_items')->get()->toJson()];
        $this->ownerLogin();
        $this->get(route('owner.requests.index', ['per_page' => 10]))->assertOk()->assertSee('كل النتائج المطابقة (28)');
        $this->preview()->assertOk()->assertSee('28 طلب');
        $consent = $this->consent();
        $this->post(route('owner.requests.confirm'), $consent)->assertRedirect();
        $this->post(route('owner.requests.confirm'), $consent)->assertRedirect();
        $this->assertDatabaseCount('preliminary_request_reviews', 28);
        $this->assertDatabaseCount('preliminary_request_review_events', 28);
        $this->assertSame(1, DB::table('preliminary_request_review_events')->distinct()->count('bulk_id'));
        $this->assertSame(28, DB::table('preliminary_request_review_events')->where('owner_id', $this->owner->id)->count());
        $this->assertSame($history, [DB::table('checkout_intents')->get()->toJson(), DB::table('checkout_intent_items')->get()->toJson()]);
        $this->assertSame(28, DB::table('preliminary_request_reviews')->whereIn('checkout_intent_id', $ids)->where('review_status', 'under_review')->count());
    }

    public function test_changed_selected_version_or_filter_membership_rejects_whole_group_without_partial_events(): void
    {
        $ids = $this->requests(2);
        $this->ownerLogin();
        $this->preview()->assertOk();
        app(ReviewRequestAction::class)->execute($ids[1], $this->owner, (string) Str::uuid7(), '0', 'note', note: 'نشاط أحدث');
        $this->post(route('owner.requests.confirm'), $this->consent())->assertStatus(409);
        $this->assertDatabaseCount('preliminary_request_review_events', 1);
        $this->assertDatabaseCount('preliminary_request_reviews', 1);
        $this->preview(['filters' => ['currency' => 'USD']])->assertOk();
        // Only synthetic test submission metadata changes to exercise captured membership validation.
        DB::table('checkout_intents')->where('id', $ids[0])->update(['currency' => 'SYP']);
        $this->post(route('owner.requests.confirm'), $this->consent())->assertStatus(409);
        $this->assertDatabaseCount('preliminary_request_review_events', 1);
    }

    public function test_single_multiple_selection_exact_cap_and_oversize_are_validated(): void
    {
        $ids = $this->requests(101);
        $this->ownerLogin();
        $this->preview()->assertStatus(422);
        $this->preview(['scope' => 'ids', 'ids' => array_slice($ids, 0, 100), 'filters' => []])->assertOk()->assertSee('100 طلب');
        $this->post(route('owner.requests.confirm'), $this->consent())->assertRedirect();
        $this->assertDatabaseCount('preliminary_request_review_events', 100);
        $this->preview(['scope' => 'ids', 'ids' => [$ids[100]], 'filters' => []])->assertOk()->assertSee('1 طلب');
        $this->preview(['scope' => 'ids', 'ids' => [$ids[100], 'bad']])->assertStatus(422);
        $this->preview(['target' => 'reviewed'])->assertStatus(422);
        $this->preview(['target' => 'paid'])->assertStatus(422);
        $this->preview(['filters' => ['reviewed' => 0]])->assertStatus(422);
    }

    public function test_preview_rejects_any_ineligible_state_and_never_bulk_reopens_or_completes(): void
    {
        $ids = $this->requests(2);
        app(ReviewRequestAction::class)->execute($ids[0], $this->owner, (string) Str::uuid7(), '0', 'transition', 'under_review');
        app(ReviewRequestAction::class)->execute($ids[0], $this->owner, (string) Str::uuid7(), '1', 'transition', 'reviewed');
        $this->ownerLogin();
        $this->preview(['filters' => []])->assertStatus(409);
        $this->preview(['scope' => 'ids', 'ids' => [$ids[0]], 'filters' => [], 'target' => 'reviewed'])->assertStatus(422);
        $this->preview(['scope' => 'ids', 'ids' => [$ids[1]], 'filters' => [], 'target' => 'awaiting_contact'])->assertOk();
        $this->post(route('owner.requests.confirm'), $this->consent())->assertRedirect();
        $this->assertDatabaseHas('preliminary_request_reviews', ['checkout_intent_id' => $ids[1], 'review_status' => 'awaiting_contact']);
        $this->assertDatabaseCount('preliminary_request_review_events', 3);
    }

    public function test_confirmation_requires_consent_bound_token_and_expiry_and_new_preview_replaces_old(): void
    {
        $this->requests(1);
        $this->ownerLogin();
        $this->preview()->assertOk();
        $first = $this->consent();
        $this->post(route('owner.requests.confirm'), ['token' => $first['token']])->assertStatus(422);
        $this->post(route('owner.requests.confirm'), ['token' => (string) Str::uuid7(), 'confirm' => 1])->assertStatus(409);
        $this->preview()->assertOk();
        $this->post(route('owner.requests.confirm'), $first)->assertStatus(409);
        $expired = $this->consent();
        $this->travel(11)->minutes();
        $this->post(route('owner.requests.confirm'), $expired)->assertStatus(409);
        $this->assertDatabaseCount('preliminary_request_review_events', 0);
    }

    public function test_database_failure_after_first_bulk_target_rolls_back_all_reviews_and_audit_events(): void
    {
        $ids = $this->requests(2);
        $this->ownerLogin();
        $this->preview()->assertOk();
        DB::listen(function ($query) use ($ids): void {
            if (str_starts_with($query->sql, 'insert into `preliminary_request_reviews`') && in_array($ids[1], $query->bindings, true)) {
                DB::table('preliminary_request_review_events')->insert(['note' => 'force real MySQL failure after first target']);
            }
        });
        $this->post(route('owner.requests.confirm'), $this->consent())->assertStatus(500)->assertDontSee('SQLSTATE');
        $this->assertDatabaseCount('preliminary_request_reviews', 0);
        $this->assertDatabaseCount('preliminary_request_review_events', 0);
        $this->assertDatabaseCount('checkout_intents', 2);
    }
}
