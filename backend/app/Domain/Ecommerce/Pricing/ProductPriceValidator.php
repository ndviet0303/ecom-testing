<?php

namespace App\Domain\Ecommerce\Pricing;

use App\Domain\Ecommerce\Exception\InvalidDomainArgumentException;

final class ProductPriceValidator
{
    /**
     * @param  int  $basePriceCents  Giá niêm yết (>= 0, > 0 khi bán)
     * @param  int|null  $salePriceCents  Giá khuyến mãi hoặc null nếu không giảm
     */
    public function validate(int $basePriceCents, ?int $salePriceCents): void
    {
        if ($basePriceCents <= 0) {
            throw new InvalidDomainArgumentException('Base price must be positive.');
        }

        if ($salePriceCents === null) {
            return;
        }

        if ($salePriceCents < 0) {
            throw new InvalidDomainArgumentException('Sale price cannot be negative.');
        }

        if ($salePriceCents > $basePriceCents) {
            throw new InvalidDomainArgumentException('Sale price cannot exceed base price.');
        }
    }
}
