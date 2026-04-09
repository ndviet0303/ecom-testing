<?php

namespace App\Domain\Ecommerce\Promotion;

use App\Domain\Ecommerce\Exception\InvalidDomainArgumentException;

final class CouponApplicator
{
    /**
     * Áp dụng giảm giá cố định (cents). Không cho tổng sau giảm âm.
     *
     * @return int Số tiền giảm thực tế (0 .. discountCents)
     */
    public function appliedDiscountCents(int $subtotalCents, int $minimumSubtotalCents, int $discountCents): int
    {
        if ($subtotalCents < 0 || $minimumSubtotalCents < 0 || $discountCents < 0) {
            throw new InvalidDomainArgumentException('Amounts cannot be negative.');
        }

        if ($subtotalCents < $minimumSubtotalCents) {
            throw new InvalidDomainArgumentException('Order subtotal is below coupon minimum.');
        }

        return min($discountCents, $subtotalCents);
    }

    public function totalAfterDiscountCents(int $subtotalCents, int $minimumSubtotalCents, int $discountCents): int
    {
        $applied = $this->appliedDiscountCents($subtotalCents, $minimumSubtotalCents, $discountCents);

        return $subtotalCents - $applied;
    }
}
