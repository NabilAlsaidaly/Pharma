<?php

namespace App\Domains\Orders\Infrastructure\Persistence\Models;

use App\Domains\Orders\Domain\Review\ReviewStatus;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

/**
 * @property ReviewStatus $review_status
 * @property string $lock_version
 */
class PreliminaryRequestReview extends Model
{
    use HasUuids;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['review_status' => ReviewStatus::class, 'lock_version' => 'string', 'review_started_at' => 'immutable_datetime',
            'review_completed_at' => 'immutable_datetime', 'last_activity_at' => 'immutable_datetime'];
    }
}
