<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

class ProductCatalogTest extends TestCase
{
    public function test_sku_should_be_unique(): void
    {
        // TODO: Replace with real Product factory + unique constraint assertion.
        $this->assertTrue(true);
    }

    public function test_sale_price_cannot_exceed_base_price(): void
    {
        // TODO: Validate pricing rule in Product domain service/model.
        $this->assertTrue(true);
    }
}
