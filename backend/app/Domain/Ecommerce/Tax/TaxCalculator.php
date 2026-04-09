<?php

namespace App\Domain\Ecommerce\Tax;

use App\Domain\Ecommerce\Exception\InvalidDomainArgumentException;

final class TaxCalculator
{
    /**
     * @param  int  $taxableAmountCents  Tiền chịu thuế
     * @param  int  $rateBasisPoints  Thuế suất: 10000 = 100% (vd VAT 10% = 1000)
     */
    public function taxAmountCents(int $taxableAmountCents, int $rateBasisPoints): int
    {
        if ($taxableAmountCents < 0 || $rateBasisPoints < 0) {
            throw new InvalidDomainArgumentException('Tax inputs cannot be negative.');
        }

        return (int) round($taxableAmountCents * $rateBasisPoints / 10000);
    }
}
