<?php

namespace App\Domains\Gifts\Infrastructure;

use App\Domains\Catalog\Application\Ports\CatalogSelectionReader;
use App\Domains\Catalog\Domain\Enums\AvailabilityStatus;

/** Narrow Catalog read integration; package pricing never uses Catalog unit prices. */
final class GiftCatalog
{
    public function inspect(array $components, string $availability, bool $lock = false): array
    {
        $linked = array_values(array_filter($components, fn ($c) => $c['type'] === 'catalog'));
        $products = app(CatalogSelectionReader::class)->read(array_column($linked, 'product_id'), array_column($linked, 'variant_id'), $lock);
        $eligible = $availability !== 'unavailable';
        $review = $availability === 'on_request';
        $valid = [];
        foreach ($linked as $c) {
            $p = $products[$c['product_id']] ?? null;
            $v = $p['variants'][$c['variant_id']] ?? null;
            $structure = $p && ($p['variants_count'] > 0 ? $v !== null : $c['variant_id'] === null);
            $state = $structure ? ($v ? $p['availability']->effectiveWith($v['availability']) : $p['availability']) : AvailabilityStatus::Unavailable;
            $ok = $structure && $p['visible'] && $state !== AvailabilityStatus::Unavailable;
            $valid[$c['selection_key'] ?? 'c:'.$c['product_id'].':'.($c['variant_id'] ?? '-')] = $ok;
            $eligible = $eligible && $ok;
            $review = $review || ($ok && $state === AvailabilityStatus::OnRequest);
        }

        return compact('eligible', 'review', 'products', 'valid');
    }
}
