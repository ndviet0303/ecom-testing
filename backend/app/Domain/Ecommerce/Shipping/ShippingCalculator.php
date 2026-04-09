<?php

namespace App\Domain\Ecommerce\Shipping;

use App\Domain\Ecommerce\Exception\InvalidDomainArgumentException;

final class ShippingCalculator
{
    /**
     * @param  int  $weightGrams  Khối lượng gói hàng
     * @param  int  $ratePerKgCents  Phí mỗi kg (làm tròn theo kg)
     * @param  int  $subtotalCents  Giá trị đơn (để freeship)
     * @param  int  $freeShippingFromSubtotalCents  Từ mức này miễn phí ship (0 = không freeship)
     */
    public function quoteCents(
        int $weightGrams,
        int $ratePerKgCents,
        int $subtotalCents,
        int $freeShippingFromSubtotalCents = 0
    ): int {
        if ($weightGrams < 0 || $ratePerKgCents < 0 || $subtotalCents < 0 || $freeShippingFromSubtotalCents < 0) {
            throw new InvalidDomainArgumentException('Shipping inputs cannot be negative.');
        }

        if ($freeShippingFromSubtotalCents > 0 && $subtotalCents >= $freeShippingFromSubtotalCents) {
            return 0;
        }

        $kg = $weightGrams / 1000;
        $billableKg = (int) ceil($kg);

        return $billableKg * $ratePerKgCents;
    }
}
