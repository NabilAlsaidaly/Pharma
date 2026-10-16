<?php

namespace Tests\Feature\Gifts;

use App\Domains\Gifts\Application\ReviewGiftRequest;
use App\Domains\Gifts\Infrastructure\GiftRequests;
use App\Domains\Identity\Infrastructure\Persistence\Models\Customer;
use App\Domains\Orders\Domain\Review\ReviewStatus;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Tests\GiftTestCase;

class GiftReviewTest extends GiftTestCase
{
    public function test_review_interface_missing_state_transitions_reopen_notes_and_private_history(): void
    {
        $id = $this->gift();
        $rid = $this->submitGift($id)['id'];
        $original = DB::table('gift_requests')->where('id', $rid)->first();
        $this->ownerLogin();
        $this->get(route('owner.gifts.requests'))->assertOk()->assertDontSee('عنوان خاص');
        $this->get(route('owner.gifts.requests.show', ['id' => $rid]))->assertOk()->assertSee('عنوان خاص');
        $this->assertDatabaseCount('gift_request_reviews', 0);
        $url = route('owner.gifts.review', ['id' => $rid]);
        $note = $this->giftCommand(extra: ['kind' => 'note', 'note' => 'ملاحظة خاصة']);
        unset($note['action']);
        $this->post($url, $note)->assertRedirect();
        $this->post($url, $note)->assertRedirect();
        $this->post($url, array_replace($note, ['note' => 'تغيير']))->assertStatus(409);
        $this->post($url, ['operation_id' => (string) Str::uuid7(), 'expected_version' => '1', 'kind' => 'transition', 'target' => 'reviewed'])->assertStatus(409);
        $version = 1;
        foreach (['under_review', 'awaiting_contact', 'under_review', 'reviewed'] as $target) {
            $this->post($url, ['operation_id' => (string) Str::uuid7(), 'expected_version' => (string) $version++, 'kind' => 'transition', 'target' => $target])->assertRedirect();
        }
        $reopen = ['operation_id' => (string) Str::uuid7(), 'expected_version' => '5', 'kind' => 'transition', 'target' => 'under_review'];
        $this->post($url, $reopen)->assertStatus(422);
        $this->post($url, $reopen + ['note' => 'سبب إعادة الفتح'])->assertRedirect();
        $this->post($url, ['operation_id' => (string) Str::uuid7(), 'expected_version' => '6', 'kind' => 'follow_up', 'note' => 'وصفي اليدوي الخاص'])->assertRedirect();
        $this->assertDatabaseCount('gift_review_events', 7);
        $this->assertDatabaseHas('gift_request_reviews', ['request_id' => $rid, 'review_status' => 'under_review', 'lock_version' => 7, 'review_completed_at' => null]);
        $this->assertEquals($original, DB::table('gift_requests')->where('id', $rid)->first());
        $this->get(route('owner.gifts.requests.show', ['id' => $rid]))->assertOk()->assertSee('سبب إعادة الفتح')->assertSee($this->owner->display_name);
        $this->get(route('owner.gifts.history'))->assertOk()->assertDontSee('ملاحظة خاصة')->assertDontSee('عنوان خاص');
        $customer = Customer::query()->first();
        $this->actingAs($customer, 'customer');
        $this->getJson('/api/v1/gifts/requests/'.$rid)->assertOk()->assertDontSee('ملاحظة خاصة')->assertDontSee('review_status')->assertDontSee('وصفي اليدوي');
    }

    public function test_all_state_pairs_use_the_exact_approved_shared_rule(): void
    {
        $rid = $this->submitGift($this->gift())['id'];
        foreach (ReviewStatus::cases() as $before) {
            foreach (ReviewStatus::cases() as $after) {
                DB::beginTransaction();
                try {
                    $reviewId = (string) Str::uuid7();
                    DB::table('gift_request_reviews')->insert(['id' => $reviewId, 'request_id' => $rid, 'review_status' => $before->value, 'lock_version' => 1, 'last_activity_at' => now()]);
                    $input = ['operation_id' => (string) Str::uuid7(), 'expected_version' => '1', 'kind' => 'transition', 'target' => $after->value, 'note' => 'سبب موثق'];
                    try {
                        app(ReviewGiftRequest::class)->execute($rid, $this->owner, $input);
                        $this->assertTrue($before->allows($after));
                    } catch (HttpExceptionInterface $e) {
                        $this->assertFalse($before->allows($after));
                        $this->assertSame(409, $e->getStatusCode());
                    }
                } finally {
                    DB::rollBack();
                }
            }
        }
        $this->assertDatabaseCount('gift_review_events', 0);
    }

    public function test_review_event_sql_failure_rolls_back_aggregate_and_audit(): void
    {
        $rid = $this->submitGift($this->gift())['id'];
        $armed = true;
        DB::listen(function ($q) use (&$armed): void {
            if ($armed && str_starts_with($q->sql, 'insert into `gift_review_events`')) {
                $armed = false;
                DB::table('gift_change_events')->insert(['request_id' => 'private-fixture']);
            }
        });
        $this->ownerLogin();
        $this->post(route('owner.gifts.review', ['id' => $rid]), ['operation_id' => (string) Str::uuid7(), 'expected_version' => '0', 'kind' => 'note', 'note' => 'private-fixture'])->assertStatus(500)->assertDontSee('SQLSTATE')->assertDontSee('private-fixture');
        $this->assertDatabaseCount('gift_request_reviews', 0);
        $this->assertDatabaseCount('gift_review_events', 0);
        $this->assertDatabaseCount('gift_change_events', 3);
        $detail = app(GiftRequests::class)->detail($rid);
        $this->assertNull($detail['review']);
    }
}
