<?php

namespace App\Domains\Identity\Application\DTOs;

use App\Domains\Identity\Domain\Enums\CustomerAccountStatus;
use DateTimeImmutable;

readonly class CustomerAuthenticationData
{
    public function __construct(
        public string $id,
        public string $name,
        public string $email,
        public ?string $avatarUrl,
        public CustomerAccountStatus $accountStatus,
        public ?DateTimeImmutable $lastLoginAt,
    ) {}
}
