<?php

namespace App\Domain\Ecommerce\Cart;

use App\Domain\Ecommerce\Exception\InvalidDomainArgumentException;

final class CartLine
{
    public function __construct(
        public readonly string $sku,
        public readonly int $quantity,
        public readonly int $unitPriceCents
    ) {
        if ($sku === '') {
            throw new InvalidDomainArgumentException('Line SKU cannot be empty.');
        }

        if ($quantity <= 0) {
            throw new InvalidDomainArgumentException('Line quantity must be positive.');
        }

        if ($unitPriceCents < 0) {
            throw new InvalidDomainArgumentException('Unit price cannot be negative.');
        }
    }

    public function lineTotalCents(): int
    {
        return $this->quantity * $this->unitPriceCents;
    }
}
