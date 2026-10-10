<?php

namespace App\Domains\Catalog\Domain\Rules;

use InvalidArgumentException;

final class CatalogPrice
{
    public static function normalize(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }
        if ((! is_string($value) && ! is_int($value)) || ! preg_match('/^\d{1,16}(?:\.\d{1,2})?$/D', (string) $value)) {
            throw new InvalidArgumentException('Catalog prices require a non-negative decimal string within DECIMAL(18,2).');
        }
        [$whole, $fraction] = array_pad(explode('.', (string) $value, 2), 2, '');

        return (ltrim($whole, '0') ?: '0').'.'.str_pad($fraction, 2, '0');
    }
}
