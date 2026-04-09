<?php

namespace Tests\Unit\Ecommerce;

use App\Domain\Ecommerce\Exception\InvalidDomainArgumentException;
use App\Domain\Ecommerce\Tax\TaxCalculator;
use PHPUnit\Framework\TestCase;

class TaxCalculatorTest extends TestCase
{
    private TaxCalculator $calculator;

    protected function setUp(): void
    {
        parent::setUp();
        $this->calculator = new TaxCalculator;
    }

    public function test_vat_ten_percent(): void
    {
        // 10% = 1000 bps
        $this->assertSame(1_000, $this->calculator->taxAmountCents(10_000, 1_000));
    }

    public function test_rounds_half_up(): void
    {
        $this->assertSame(2, $this->calculator->taxAmountCents(33, 500)); // 16.5 -> 17
    }

    public function test_rejects_negative_taxable(): void
    {
        $this->expectException(InvalidDomainArgumentException::class);
        $this->calculator->taxAmountCents(-1, 100);
    }
}
