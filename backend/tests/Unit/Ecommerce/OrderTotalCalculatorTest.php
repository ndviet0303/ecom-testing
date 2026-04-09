<?php

namespace Tests\Unit\Ecommerce;

use App\Domain\Ecommerce\Checkout\OrderTotalCalculator;
use App\Domain\Ecommerce\Exception\InvalidDomainArgumentException;
use PHPUnit\Framework\TestCase;

class OrderTotalCalculatorTest extends TestCase
{
    private OrderTotalCalculator $calculator;

    protected function setUp(): void
    {
        parent::setUp();
        $this->calculator = new OrderTotalCalculator;
    }

    public function test_grand_total_formula(): void
    {
        $total = $this->calculator->grandTotalCents(
            subtotalCents: 100_000,
            discountCents: 10_000,
            shippingCents: 5_000,
            taxCents: 8_000
        );
        $this->assertSame(103_000, $total);
    }

    public function test_rejects_discount_exceeding_subtotal(): void
    {
        $this->expectException(InvalidDomainArgumentException::class);
        $this->calculator->grandTotalCents(5_000, 6_000, 0, 0);
    }
}
