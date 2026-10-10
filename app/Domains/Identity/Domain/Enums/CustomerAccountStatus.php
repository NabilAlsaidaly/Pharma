<?php

namespace App\Domains\Identity\Domain\Enums;

enum CustomerAccountStatus: string
{
    case Active = 'active';
    case Suspended = 'suspended';
}
