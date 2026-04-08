<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

class PricingPromotionTest extends TestCase
{
    public function test_coupon_should_respect_minimum_order_amount(): void
    {
        // TODO: Assert coupon validation for minimum order value.
        $this->assertTrue(true);
    }

    public function test_discounted_total_should_never_be_negative(): void
    {
        // TODO: Assert final total floor at zero after promotions.
        $this->assertTrue(true);
    }
}
