<?php

namespace App\Domain\Ecommerce\Cart;

final class ShoppingCart
{
    /** @var list<CartLine> */
    private array $lines = [];

    public function addLine(CartLine $line): void
    {
        $this->lines[] = $line;
    }

    /** @return list<CartLine> */
    public function lines(): array
    {
        return $this->lines;
    }

    public function subtotalCents(): int
    {
        $sum = 0;

        foreach ($this->lines as $line) {
            $sum += $line->lineTotalCents();
        }

        return $sum;
    }

    public function totalQuantity(): int
    {
        $qty = 0;

        foreach ($this->lines as $line) {
            $qty += $line->quantity;
        }

        return $qty;
    }
}
