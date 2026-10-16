<?php

namespace App\Domains\Orders\Application\Review;

use App\Domains\ManagementIdentity\Infrastructure\Persistence\Models\ManagementUser;
use App\Domains\Orders\Domain\Review\ReviewStatus;
use App\Domains\Orders\Infrastructure\Persistence\Models\CheckoutIntent;
use App\Domains\Orders\Infrastructure\Persistence\Models\PreliminaryRequestReview;
use App\Domains\Orders\Infrastructure\Persistence\Models\PreliminaryRequestReviewEvent;
use App\Domains\Orders\Infrastructure\Persistence\Queries\OwnerRequestReviewReader;
use Illuminate\Support\Facades\DB;

final class BulkReviewRequests
{
    public const CAP = 100;

    public function execute(array $preview, ManagementUser $owner, string $requestId = ''): int
    {
        abort_unless(count($preview['versions']) > 0 && count($preview['versions']) <= self::CAP, 422);
        $target = ReviewStatus::tryFrom($preview['target']);
        abort_unless(in_array($target, [ReviewStatus::UnderReview, ReviewStatus::AwaitingContact], true), 422);

        return DB::transaction(function () use ($preview, $owner, $requestId, $target): int {
            $ids = array_keys($preview['versions']);
            sort($ids);
            $headers = CheckoutIntent::query()->whereKey($ids)->orderBy('id')->lockForUpdate()->get(['id']);
            abort_unless($headers->count() === count($ids), 409);
            $reviews = PreliminaryRequestReview::query()->whereIn('checkout_intent_id', $ids)->orderBy('checkout_intent_id')->lockForUpdate()->get()->keyBy('checkout_intent_id');
            $committed = PreliminaryRequestReviewEvent::query()->where('owner_id', $owner->id)->whereIn('operation_id', array_values($preview['operations']))->count();
            abort_if($committed !== 0 && $committed !== count($ids), 409);
            if ($committed === 0) {
                // Preview-time explicit snapshot: membership rechecked only for captured IDs, never add new rows.
                $matching = app(OwnerRequestReviewReader::class)->query($preview['filters'])->whereIn('ci.id', $ids)->orderBy('ci.id')->pluck('ci.id')->all();
                abort_unless($matching === $ids, 409, 'تغيّرت السجلات المحددة. أعيدي المعاينة.');
                foreach ($ids as $id) {
                    $review = $reviews->get($id);
                    abort_unless(($review->lock_version ?? '0') === (string) $preview['versions'][$id], 409, 'تغيّر إصدار مراجعة. أعيدي المعاينة.');
                    abort_unless(($review->review_status ?? ReviewStatus::New)->allows($target, true), 409, 'المجموعة تتضمن انتقالاً غير مسموح.');
                }
            }
            foreach ($ids as $id) {
                app(ReviewRequestAction::class)->execute($id, $owner, $preview['operations'][$id], (string) $preview['versions'][$id], 'transition', $target->value, null, $preview['bulk_id'], $requestId);
            }

            return count($ids);
        }, 3);
    }
}
