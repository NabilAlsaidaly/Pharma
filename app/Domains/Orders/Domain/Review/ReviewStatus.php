<?php

namespace App\Domains\Orders\Domain\Review;

enum ReviewStatus: string
{
    case New = 'new';
    case UnderReview = 'under_review';
    case AwaitingContact = 'awaiting_contact';
    case Reviewed = 'reviewed';

    public function label(): string
    {
        return match ($this) {
            self::New => 'جديد', self::UnderReview => 'قيد المراجعة',
            self::AwaitingContact => 'يحتاج إلى تواصل', self::Reviewed => 'تمت المراجعة',
        };
    }

    public function allows(self $next, bool $bulk = false): bool
    {
        if ($bulk && ($this === self::Reviewed || ! in_array($next, [self::UnderReview, self::AwaitingContact], true))) {
            return false;
        }

        return in_array($next, match ($this) {
            self::New => [self::UnderReview, self::AwaitingContact],
            self::UnderReview => [self::AwaitingContact, self::Reviewed],
            self::AwaitingContact, self::Reviewed => [self::UnderReview],
        }, true);
    }
}
