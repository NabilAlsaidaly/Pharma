<?php

namespace App\Domains\Cart\Domain\Rules;

use InvalidArgumentException;

final class CartQuantity
{
    public const MAX = 2147483647;

    public static function valid(mixed $value): bool
    {
        return is_int($value) && $value >= 1 && $value <= self::MAX;
    }

    public static function sum(int $left, int $right): int
    {
        if (! self::valid($left) || ! self::valid($right) || $left > self::MAX - $right) {
            throw new InvalidArgumentException('Quantity overflow.');
        }

        return $left + $right;
    }
}
