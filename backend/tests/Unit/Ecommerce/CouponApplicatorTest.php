<?php

namespace Tests\Unit\Ecommerce;

use App\Domain\Ecommerce\Exception\InvalidDomainArgumentException;
use App\Domain\Ecommerce\Promotion\CouponApplicator;
use PHPUnit\Framework\TestCase;

class CouponApplicatorTest extends TestCase
{
    private CouponApplicator $applicator;

    protected function setUp(): void
    {
        parent::setUp();
        $this->applicator = new CouponApplicator;
    }

    public function test_fails_when_subtotal_below_minimum(): void
    {
        $this->expectException(InvalidDomainArgumentException::class);
        $this->applicator->appliedDiscountCents(5_000, 10_000, 1_000);
    }

    public function test_applies_full_discount_when_subtotal_allows(): void
    {
        $applied = $this->applicator->appliedDiscountCents(20_000, 10_000, 3_000);
        $this->assertSame(3_000, $applied);
    }

    public function test_caps_discount_at_subtotal(): void
    {
        $total = $this->applicator->totalAfterDiscountCents(2_000, 0, 5_000);
        $this->assertSame(0, $total);
    }

    public function test_total_after_discount_never_negative(): void
    {
        $applied = $this->applicator->appliedDiscountCents(1_000, 0, 2_000);
        $this->assertSame(1_000, $applied);
        $this->assertSame(0, $this->applicator->totalAfterDiscountCents(1_000, 0, 2_000));
    }
}
