<?php

namespace App\Domains\Cart\Domain\Exceptions;

use RuntimeException;

final class CartConflict extends RuntimeException
{
    public function __construct(public readonly string $reason)
    {
        parent::__construct('Cart conflict.');
    }
}
