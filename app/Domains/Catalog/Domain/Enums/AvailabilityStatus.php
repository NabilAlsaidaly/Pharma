<?php

namespace App\Domains\Catalog\Domain\Enums;

enum AvailabilityStatus: string
{
    case Available = 'available';
    case OnRequest = 'on_request';
    case Unavailable = 'unavailable';

    public function effectiveWith(self $variant): self
    {
        if ($this === self::Unavailable || $variant === self::Unavailable) {
            return self::Unavailable;
        }

        return $this === self::OnRequest || $variant === self::OnRequest ? self::OnRequest : self::Available;
    }
}
