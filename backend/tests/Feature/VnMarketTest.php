<?php

namespace Tests\Feature;

use Tests\TestCase;

class VnMarketTest extends TestCase
{
    public function test_sepay_placeholder_returns_vn_market_message(): void
    {
        $this->getJson('/api/v1/payments/vietqr')
            ->assertOk()
            ->assertJsonPath('provider', 'sepay')
            ->assertJsonStructure(['webhook_post_url', 'configured_for_qr', 'checkout_field']);
    }

    public function test_vn_config_has_country_and_currency(): void
    {
        $this->assertSame('VN', config('vn.country_code'));
        $this->assertSame('VND', config('vn.currency_code'));
        $this->assertSame(1000, config('vn.default_vat_basis_points'));
    }
}
