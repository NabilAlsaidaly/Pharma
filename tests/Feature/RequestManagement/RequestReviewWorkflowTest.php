<?php

namespace Tests\Feature\RequestManagement;

use App\Domains\Orders\Application\Review\ReviewRequestAction;
use App\Domains\Orders\Domain\Review\ReviewStatus;
use App\Domains\Orders\Infrastructure\Persistence\Models\PreliminaryRequestReview;
use App\Domains\Orders\Infrastructure\Persistence\Models\PreliminaryRequestReviewEvent;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\OwnerDashboardTestCase;

class RequestReviewWorkflowTest extends OwnerDashboardTestCase
{
    private function payload(array $extra = []): array
    {
        return array_replace(['operation_id' => (string) Str::uuid7(), 'expected_version' => '0', 'kind' => 'note', 'note' => 'ملاحظة داخلية تجريبية'], $extra);
    }

    public function test_gets_infer_new_without_backfill_and_original_header_items_stay_immutable(): void
    {
        $id = $this->requestFixture();
        $before = [DB::table('checkout_intents')->get()->toJson(), DB::table('checkout_intent_items')->get()->toJson()];
        $this->ownerLogin();
        $this->get(route('owner.requests.index'))->assertOk()->assertSee('جديد')->assertDontSee('Private-contact-name-sentinel');
        $this->get(route('owner.requests.show', ['id' => $id]))->assertOk()->assertSee('Private-address-sentinel')->assertSee('اكتمال المراجعة لا يعني تأكيد الطلب أو استلام الدفع');
        $this->assertDatabaseCount('preliminary_request_reviews', 0);
        $this->assertDatabaseCount('preliminary_request_review_events', 0);
        $url = route('owner.requests.review', ['id' => $id]);
        $this->post($url, $this->payload(['kind' => 'transition', 'target' => 'under_review', 'note' => null]))->assertRedirect();
        $this->post($url, $this->payload(['expected_version' => '1', 'kind' => 'transition', 'target' => 'reviewed', 'note' => 'اكتمل الفحص الإداري']))->assertRedirect();
        $review = PreliminaryRequestReview::query()->sole();
        $this->assertSame(ReviewStatus::Reviewed, $review->review_status);
        $this->assertNotNull($review->review_started_at);
        $this->assertNotNull($review->review_completed_at);
        $this->assertSame($this->owner->id, $review->last_reviewed_by_owner_id);
        $this->assertSame($before, [DB::table('checkout_intents')->get()->toJson(), DB::table('checkout_intent_items')->get()->toJson()]);
        $this->assertSame('submitted', DB::table('checkout_intents')->sole()->status);
    }

    public static function transitions(): array
    {
        $cases = [];
        foreach (ReviewStatus::cases() as $before) {
            foreach (ReviewStatus::cases() as $after) {
                $allowed = in_array($before->value.'>'.$after->value, ['new>under_review', 'new>awaiting_contact', 'under_review>awaiting_contact', 'under_review>reviewed', 'awaiting_contact>under_review', 'reviewed>under_review'], true);
                $cases[$before->value.' to '.$after->value] = [$before->value, $after->value, $allowed];
            }
        }

        return $cases;
    }

    #[DataProvider('transitions')]
    public function test_exact_approved_transition_matrix(string $before, string $after, bool $allowed): void
    {
        $id = $this->requestFixture();
        if ($before !== 'new') {
            PreliminaryRequestReview::query()->create(['checkout_intent_id' => $id, 'review_status' => $before, 'lock_version' => '1']);
        }
        $version = $before === 'new' ? '0' : '1';
        try {
            app(ReviewRequestAction::class)->execute($id, $this->owner, (string) Str::uuid7(), $version, 'transition', $after, 'سبب إداري موثّق');
            $this->assertTrue($allowed);
            $this->assertSame($after, PreliminaryRequestReview::query()->sole()->review_status->value);
            $this->assertDatabaseCount('preliminary_request_review_events', 1);
        } catch (HttpException $exception) {
            $this->assertFalse($allowed);
            $this->assertSame(409, $exception->getStatusCode());
            $this->assertDatabaseCount('preliminary_request_review_events', 0);
        }
        $this->assertSame('submitted', DB::table('checkout_intents')->sole()->status);
    }

    public function test_notes_follow_up_replay_payload_conflict_stale_version_and_reopen_reason(): void
    {
        $id = $this->requestFixture();
        $this->ownerLogin();
        $url = route('owner.requests.review', ['id' => $id]);
        $first = $this->payload(['note' => "نص عربي <script>private_sentinel()</script> '; DROP TABLE checkout_intents; --"]);
        $this->post($url, $first)->assertRedirect();
        $this->assertNull(PreliminaryRequestReview::query()->sole()->review_started_at);
        $this->post($url, $this->payload(['expected_version' => '1', 'kind' => 'follow_up', 'note' => 'وثّقت محاولة تواصل يدوية']))->assertRedirect();
        $this->post($url, $first)->assertRedirect();
        $this->assertDatabaseCount('preliminary_request_review_events', 2);
        $this->post($url, array_replace($first, ['note' => 'تغيير غير مسموح']))->assertStatus(409);
        $this->post($url, $this->payload(['expected_version' => '1']))->assertStatus(409);
        $this->get(route('owner.requests.show', ['id' => $id]))->assertOk()->assertSee('&lt;script&gt;', false)->assertDontSee('<script>private_sentinel()', false)->assertSee('وثّقت محاولة تواصل يدوية');
        $this->post($url, $this->payload(['expected_version' => '2', 'kind' => 'transition', 'target' => 'under_review', 'note' => null]))->assertRedirect();
        $this->post($url, $this->payload(['expected_version' => '3', 'kind' => 'transition', 'target' => 'reviewed', 'note' => null]))->assertRedirect();
        $this->post($url, $this->payload(['expected_version' => '4', 'kind' => 'transition', 'target' => 'under_review', 'note' => null]))->assertUnprocessable();
        $this->post($url, $this->payload(['expected_version' => '4', 'kind' => 'transition', 'target' => 'under_review', 'note' => 'معلومة جديدة تستدعي إعادة الفحص']))->assertRedirect();
        $this->assertNull(PreliminaryRequestReview::query()->sole()->review_completed_at);
        $this->assertDatabaseHas('preliminary_request_review_events', ['event_type' => 'review_reopened', 'owner_id' => $this->owner->id]);
        $this->assertDatabaseCount('preliminary_request_review_events', 5);
    }

    public static function badNotes(): array
    {
        return [[''], ['   '], [str_repeat('ع', 2001)], ["bad\0text"], ["bad\u{202e}text"], [['bad']]];
    }

    #[DataProvider('badNotes')]
    public function test_invalid_note_never_persists_or_flashes_private_input(mixed $note): void
    {
        $id = $this->requestFixture();
        $this->ownerLogin();
        $this->post(route('owner.requests.review', ['id' => $id]), $this->payload(['note' => $note]))->assertUnprocessable();
        $this->assertDatabaseCount('preliminary_request_review_events', 0);
        $this->assertFalse(session()->has('_old_input.note'));
    }

    public function test_real_audit_fk_failure_rolls_back_review_and_original_submission_is_unchanged(): void
    {
        $id = $this->requestFixture();
        $before = DB::table('checkout_intents')->sole();
        $fake = clone $this->owner;
        $fake->id = (string) Str::uuid7();
        try {
            app(ReviewRequestAction::class)->execute($id, $fake, (string) Str::uuid7(), '0', 'note', note: 'يجب التراجع');
            $this->fail('Actual Owner foreign key must reject the insert.');
        } catch (QueryException $exception) {
            $this->assertSame(1452, $exception->errorInfo[1]);
        }
        $this->assertDatabaseCount('preliminary_request_reviews', 0);
        $this->assertDatabaseCount('preliminary_request_review_events', 0);
        $this->assertEquals($before, DB::table('checkout_intents')->sole());
    }

    public function test_timeline_is_paginated_and_original_event_cannot_be_edited_or_deleted(): void
    {
        $id = $this->requestFixture();
        for ($i = 0; $i < 28; $i++) {
            app(ReviewRequestAction::class)->execute($id, $this->owner, (string) Str::uuid7(), (string) $i, 'note', note: 'ملاحظة '.$i);
        }
        $this->ownerLogin();
        $events = $this->get(route('owner.requests.show', ['id' => $id]))->assertOk()->viewData('events');
        $this->assertSame(28, $events->total());
        $this->assertCount(25, $events->items());
        $this->assertCount(3, $this->get(route('owner.requests.show', ['id' => $id, 'events_page' => 2]))->assertOk()->viewData('events')->items());
        $event = PreliminaryRequestReviewEvent::query()->firstOrFail();
        $this->delete(route('owner.requests.show', ['id' => $id]).'/events/'.$event->id)->assertNotFound();
        $this->patch(route('owner.requests.show', ['id' => $id]).'/events/'.$event->id, ['note' => 'overwrite'])->assertNotFound();
        $this->assertDatabaseCount('preliminary_request_review_events', 28);
    }
}
