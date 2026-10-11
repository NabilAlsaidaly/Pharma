<?php

namespace App\Domains\Catalog\Application\Ports;

interface CatalogSelectionReader
{
    /** @return array<string, array<string, mixed>> */
    public function read(array $productIds, array $variantIds, bool $lock = false): array;
}
