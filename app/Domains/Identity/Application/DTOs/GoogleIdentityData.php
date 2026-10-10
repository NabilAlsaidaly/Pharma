<?php

namespace App\Domains\Identity\Application\DTOs;

readonly class GoogleIdentityData
{
    public function __construct(
        public string $googleSub,
        public string $name,
        public string $email,
        public ?string $avatarUrl,
    ) {}
}
