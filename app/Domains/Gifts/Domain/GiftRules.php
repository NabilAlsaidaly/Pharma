<?php

namespace App\Domains\Gifts\Domain;

use Brick\Math\BigInteger;
use InvalidArgumentException;

final class GiftRules
{
    public static function version(mixed $value, bool $advancing = true): string
    {
        if (! is_string($value) || ! preg_match('/^(0|[1-9][0-9]{0,19})$/D', $value) || BigInteger::of($value)->isGreaterThan('18446744073709551615') || ($advancing && $value === '18446744073709551615')) {
            throw new InvalidArgumentException('Invalid gift version.');
        }

        return $value;
    }

    public static function next(string $value): string
    {
        return (string) BigInteger::of(self::version($value))->plus(1);
    }

    public static function positive(?string $price): bool
    {
        return $price !== null && preg_match('/[1-9]/', $price) === 1;
    }

    public static function key(string $name): string
    {
        return hash('sha256', mb_strtolower(preg_replace('/\s+/u', ' ', trim($name))));
    }
}
