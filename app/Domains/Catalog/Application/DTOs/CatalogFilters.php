<?php

namespace App\Domains\Catalog\Application\DTOs;

final readonly class CatalogFilters
{
    public function __construct(
        public ?string $q = null,
        public ?string $brand = null,
        public ?string $concern = null,
        public ?string $availability = null,
        public ?bool $recommended = null,
        public int $perPage = 12,
        public string $sort = 'newest',
    ) {}
}
