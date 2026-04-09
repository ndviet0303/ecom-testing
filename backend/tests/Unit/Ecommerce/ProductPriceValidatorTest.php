<?php

namespace Tests\Unit\Ecommerce;

use App\Domain\Ecommerce\Exception\InvalidDomainArgumentException;
use App\Domain\Ecommerce\Pricing\ProductPriceValidator;
use PHPUnit\Framework\TestCase;

class ProductPriceValidatorTest extends TestCase
{
    private ProductPriceValidator $validator;

    protected function setUp(): void
    {
        parent::setUp();
        $this->validator = new ProductPriceValidator;
    }

    public function test_accepts_positive_base_without_sale(): void
    {
        $this->validator->validate(10_000, null);
        $this->assertTrue(true);
    }

    public function test_rejects_non_positive_base_price(): void
    {
        $this->expectException(InvalidDomainArgumentException::class);
        $this->validator->validate(0, null);
    }

    public function test_rejects_negative_sale_price(): void
    {
        $this->expectException(InvalidDomainArgumentException::class);
        $this->validator->validate(10_000, -1);
    }

    public function test_rejects_sale_above_base(): void
    {
        $this->expectException(InvalidDomainArgumentException::class);
        $this->validator->validate(10_000, 10_001);
    }

    public function test_accepts_sale_equal_to_base(): void
    {
        $this->validator->validate(5_000, 5_000);
        $this->assertTrue(true);
    }

    public function test_accepts_zero_sale_when_allowed(): void
    {
        $this->validator->validate(10_000, 0);
        $this->assertTrue(true);
    }
}
