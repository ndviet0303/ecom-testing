<?php

namespace Tests\Unit\Ecommerce;

use App\Domain\Ecommerce\Exception\InvalidDomainArgumentException;
use App\Domain\Ecommerce\Shipping\ShippingCalculator;
use PHPUnit\Framework\TestCase;

class ShippingCalculatorTest extends TestCase
{
    private ShippingCalculator $calculator;

    protected function setUp(): void
    {
        parent::setUp();
        $this->calculator = new ShippingCalculator;
    }

    public function test_bills_by_ceiled_kilograms(): void
    {
        // 1500g = 1.5kg -> ceil 2kg, rate 5000 cents/kg -> 10000
        $cents = $this->calculator->quoteCents(1500, 5_000, 50_000, 0);
        $this->assertSame(10_000, $cents);
    }

    public function test_free_shipping_when_threshold_met(): void
    {
        $cents = $this->calculator->quoteCents(5_000, 10_000, 200_000, 150_000);
        $this->assertSame(0, $cents);
    }

    public function test_charges_when_below_threshold(): void
    {
        $cents = $this->calculator->quoteCents(1_000, 3_000, 100_000, 150_000);
        $this->assertSame(3_000, $cents);
    }

    public function test_rejects_negative_weight(): void
    {
        $this->expectException(InvalidDomainArgumentException::class);
        $this->calculator->quoteCents(-1, 1_000, 0, 0);
    }
}
