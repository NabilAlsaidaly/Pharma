<?php

namespace App\Domains\Catalog\Application\Ports;

use App\Domains\Catalog\Application\DTOs\CatalogFilters;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

interface PublicCatalogReader
{
    public function brands(int $perPage): LengthAwarePaginator;

    public function brand(string $slug): object;

    public function concerns(int $perPage): LengthAwarePaginator;

    public function concern(string $slug): object;

    public function products(CatalogFilters $filters, ?string $brandId = null, ?string $concernId = null, bool $recommendedOnly = false): LengthAwarePaginator;

    public function product(string $slug): object;
}
