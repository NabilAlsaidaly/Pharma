<?php

namespace App\Domains\Cart\Application;

use App\Domains\Catalog\Domain\Enums\AvailabilityStatus;
use Brick\Math\BigDecimal;

class CartEvaluator
{
    public function item(array $item, ?array $product): array
    {
        $base = ['id' => $item['id'], 'quantity' => $item['quantity'], 'product' => null, 'variant' => null,
            'evaluation' => ['state' => 'blocked', 'reasons' => ['selection_inaccessible']], 'pricing' => null];
        if ($product === null || ! $product['visible']) {
            return $base;
        }
        $variantId = $item['variant_id'];
        $variant = $variantId === null ? null : ($product['variants'][$variantId] ?? null);
        $structural = $product['variants_count'] > 0 ? $variant !== null : $variantId === null;
        $base['product'] = ['id' => $product['id'], 'name' => $product['name'], 'slug' => $product['slug'], 'primary_image' => $product['image']];
        $base['variant'] = $variant === null ? null : ['id' => $variant['id'], 'name' => $variant['name']];
        if (! $structural) {
            $base['evaluation']['reasons'] = ['selection_invalid'];

            return $base;
        }
        $owner = $variant ?? $product;
        $availability = $variant === null ? $product['availability'] : $product['availability']->effectiveWith($variant['availability']);
        $reasons = $availability === AvailabilityStatus::Unavailable ? ['selection_unavailable'] : [];
        $pricing = [];
        foreach (['USD' => 'price_usd', 'SYP' => 'price_syp'] as $currency => $column) {
            $price = $owner[$column];
            $valid = $price !== null && BigDecimal::of($price)->isGreaterThan(0);
            $pricing[$currency] = ['unit_price' => $price, 'line_estimate' => $valid && $availability !== AvailabilityStatus::Unavailable
                ? (string) BigDecimal::of($price)->multipliedBy($item['quantity'])->toScale(2) : null, 'price_valid' => $valid];
        }
        if (! $pricing['USD']['price_valid'] && ! $pricing['SYP']['price_valid']) {
            $reasons[] = 'selection_unpriced';
        }
        $base['evaluation'] = ['state' => $reasons === [] ? 'valid' : 'blocked', 'reasons' => $reasons,
            'availability' => $availability->value, 'requires_request' => $availability === AvailabilityStatus::OnRequest];
        $base['pricing'] = $pricing;

        return $base;
    }

    public function exclusionReason(array $item, string $currency): ?string
    {
        $price = $item['pricing'][$currency] ?? null;

        return match (true) {
            $item['product'] === null => 'selection_inaccessible',
            in_array('selection_invalid', $item['evaluation']['reasons'], true) => 'selection_invalid',
            ($item['evaluation']['availability'] ?? null) === 'unavailable' => 'selection_unavailable',
            $price === null || $price['unit_price'] === null => 'missing_price',
            ! $price['price_valid'] => 'zero_price',
            default => null,
        };
    }

    public function totals(array $items): array
    {
        $totals = [];
        foreach (['USD', 'SYP'] as $currency) {
            $sum = BigDecimal::zero()->toScale(2);
            $excluded = 0;
            $reasons = [];
            foreach ($items as $item) {
                $price = $item['pricing'][$currency] ?? null;
                $reason = $this->exclusionReason($item, $currency);
                if ($reason !== null) {
                    $excluded++;
                    $reasons[$reason] = ($reasons[$reason] ?? 0) + 1;
                } else {
                    $sum = $sum->plus($price['line_estimate']);
                }
            }
            $totals[$currency] = ['priced_subtotal' => (string) $sum, 'estimated_total' => $excluded === 0 ? (string) $sum : null,
                'complete' => $excluded === 0, 'excluded_item_count' => $excluded, 'exclusion_reasons' => (object) $reasons];
        }

        return $totals;
    }
}
