<?php

namespace App\Domains\Catalog\Infrastructure\Persistence\Concerns;

use App\Domains\Catalog\Domain\Rules\CatalogPrice;

trait HasCatalogPrices
{
    public function setPriceUsdAttribute(mixed $value): void
    {
        $this->attributes['price_usd'] = CatalogPrice::normalize($value);
    }

    public function setPriceSypAttribute(mixed $value): void
    {
        $this->attributes['price_syp'] = CatalogPrice::normalize($value);
    }
}
