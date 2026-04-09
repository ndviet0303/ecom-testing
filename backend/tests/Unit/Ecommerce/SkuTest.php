<?php

namespace Tests\Unit\Ecommerce;

use App\Domain\Ecommerce\Catalog\Sku;
use App\Domain\Ecommerce\Exception\InvalidDomainArgumentException;
use PHPUnit\Framework\TestCase;

class SkuTest extends TestCase
{
    public function test_trims_and_stores_value(): void
    {
        $sku = Sku::fromString('  ABC-123  ');
        $this->assertSame('ABC-123', $sku->value);
    }

    public function test_rejects_empty_after_trim(): void
    {
        $this->expectException(InvalidDomainArgumentException::class);
        Sku::fromString('   ');
    }

    public function test_rejects_over_max_length(): void
    {
        $this->expectException(InvalidDomainArgumentException::class);
        Sku::fromString(str_repeat('a', Sku::MAX_LENGTH + 1));
    }

    public function test_accepts_exact_max_length(): void
    {
        $raw = str_repeat('b', Sku::MAX_LENGTH);
        $sku = Sku::fromString($raw);
        $this->assertSame($raw, $sku->value);
    }
}
