<?php

namespace App\Domain\Ecommerce\Checkout;

use App\Domain\Ecommerce\Exception\InvalidDomainArgumentException;

/**
 * Tổng đơn đơn giản: subtotal - discount + shipping + tax (tax trên subtotal sau giảm, có thể tinh chỉnh sau).
 */
final class OrderTotalCalculator
{
    public function grandTotalCents(
        int $subtotalCents,
        int $discountCents,
        int $shippingCents,
        int $taxCents
    ): int {
        if ($subtotalCents < 0 || $discountCents < 0 || $shippingCents < 0 || $taxCents < 0) {
            throw new InvalidDomainArgumentException('Checkout amounts cannot be negative.');
        }

        if ($discountCents > $subtotalCents) {
            throw new InvalidDomainArgumentException('Discount cannot exceed subtotal.');
        }

        $total = $subtotalCents - $discountCents + $shippingCents + $taxCents;

        if ($total < 0) {
            throw new InvalidDomainArgumentException('Grand total cannot be negative.');
        }

        return $total;
    }
}
