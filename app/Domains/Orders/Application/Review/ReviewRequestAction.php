<?php

namespace App\Domains\Orders\Application\Review;

use App\Domains\ManagementIdentity\Infrastructure\Persistence\Models\ManagementUser;
use App\Domains\Orders\Domain\Review\ReviewStatus;
use App\Domains\Orders\Infrastructure\Persistence\Models\CheckoutIntent;
use App\Domains\Orders\Infrastructure\Persistence\Models\PreliminaryRequestReview;
use App\Domains\Orders\Infrastructure\Persistence\Models\PreliminaryRequestReviewEvent;
use Brick\Math\BigInteger;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

final class ReviewRequestAction
{
    public function execute(string $id, ManagementUser $owner, string $operation, string $version, string $kind, ?string $target = null, ?string $note = null, ?string $bulk = null, string $requestId = ''): PreliminaryRequestReviewEvent
    {
        abort_unless($owner->exists && $owner->role === 'owner' && $owner->status === 'active', 403);
        abort_unless(in_array($kind, ['transition', 'note', 'follow_up'], true), 422);
        abort_unless(Str::isUuid($operation) && preg_match('/^(0|[1-9][0-9]{0,19})$/D', $version) && BigInteger::of($version)->isLessThan('18446744073709551615'), 422);
        abort_if($note !== null && (mb_strlen($note) > 2000 || ! mb_check_encoding($note, 'UTF-8') || preg_match('/[\p{Cc}\p{Cf}]/u', str_replace(["\r", "\n", "\t"], '', $note))), 422);
        abort_if($kind !== 'transition' && ($target !== null || $note === null || trim($note) === ''), 422);
        $fingerprint = hash('sha256', json_encode([$id, $kind, $target, $note, $version, $bulk], JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE));

        return DB::transaction(function () use ($id, $owner, $operation, $version, $kind, $target, $note, $bulk, $requestId, $fingerprint): PreliminaryRequestReviewEvent {
            // Only the immutable header lock serializes first creation; never save the submission.
            CheckoutIntent::query()->whereKey($id)->lockForUpdate()->firstOrFail(['id']);
            $prior = PreliminaryRequestReviewEvent::query()->where('owner_id', $owner->id)->where('operation_id', $operation)->lockForUpdate()->first();
            if ($prior !== null) {
                abort_unless(hash_equals($prior->payload_fingerprint, $fingerprint), 409, 'استُخدم معرّف العملية لبيانات مختلفة.');

                return $prior;
            }
            $review = PreliminaryRequestReview::query()->where('checkout_intent_id', $id)->lockForUpdate()->first();
            abort_unless(($review->lock_version ?? '0') === $version, 409, 'تغيّرت المراجعة. حدّثي الصفحة وراجعي الحالة الحالية.');
            $before = $review->review_status ?? ReviewStatus::New;
            $after = $kind === 'transition' ? ReviewStatus::tryFrom($target ?? '') : $before;
            abort_unless($after !== null, 422);
            if ($kind === 'transition') {
                abort_unless($before->allows($after, $bulk !== null), 409, 'الانتقال غير مسموح من الحالة الحالية.');
                abort_if($before === ReviewStatus::Reviewed && ($note === null || trim($note) === ''), 422, 'أدخلي سبب إعادة فتح المراجعة.');
            }
            $review ??= new PreliminaryRequestReview(['checkout_intent_id' => $id, 'review_status' => ReviewStatus::New, 'lock_version' => '0']);
            $review->lock_version = (string) BigInteger::of($version)->plus(1);
            $review->review_status = $after;
            $review->last_activity_at = now();
            if ($kind === 'transition') {
                $review->last_reviewed_by_owner_id = $owner->id;
                $review->review_started_at ??= now();
                $review->review_completed_at = $after === ReviewStatus::Reviewed ? now() : null;
            }
            $review->save();
            $event = match (true) {
                $kind === 'note' => 'note_added', $kind === 'follow_up' => 'follow_up_recorded',
                $before === ReviewStatus::Reviewed => 'review_reopened', $after === ReviewStatus::Reviewed => 'review_completed',
                $before === ReviewStatus::New => 'review_started', default => 'status_changed',
            };

            return PreliminaryRequestReviewEvent::query()->create(['checkout_intent_id' => $id, 'review_id' => $review->id, 'owner_id' => $owner->id,
                'event_type' => $event, 'previous_status' => $before->value, 'new_status' => $after->value, 'note' => $note,
                'operation_id' => $operation, 'payload_fingerprint' => $fingerprint, 'resulting_version' => $review->lock_version,
                'bulk_id' => $bulk, 'request_id' => substr($requestId, 0, 100), 'created_at' => now()]);
        }, 3);
    }
}
