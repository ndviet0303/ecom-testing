<?php

namespace Tests\Unit\Ecommerce;

use App\Domain\Ecommerce\Cart\CartLine;
use App\Domain\Ecommerce\Cart\ShoppingCart;
use App\Domain\Ecommerce\Exception\InvalidDomainArgumentException;
use PHPUnit\Framework\TestCase;

class ShoppingCartTest extends TestCase
{
    public function test_subtotal_sums_line_totals(): void
    {
        $cart = new ShoppingCart;
        $cart->addLine(new CartLine('CPU-1', 2, 5_000_00));
        $cart->addLine(new CartLine('RAM-2', 1, 1_000_00));
        $this->assertSame(11_000_00, $cart->subtotalCents());
    }

    public function test_total_quantity(): void
    {
        $cart = new ShoppingCart;
        $cart->addLine(new CartLine('A', 2, 100));
        $cart->addLine(new CartLine('B', 3, 200));
        $this->assertSame(5, $cart->totalQuantity());
    }

    public function test_line_rejects_non_positive_quantity(): void
    {
        $this->expectException(InvalidDomainArgumentException::class);
        new CartLine('X', 0, 100);
    }

    public function test_line_rejects_empty_sku(): void
    {
        $this->expectException(InvalidDomainArgumentException::class);
        new CartLine('', 1, 100);
    }
}
