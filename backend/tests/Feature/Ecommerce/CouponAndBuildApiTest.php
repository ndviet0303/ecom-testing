<?php

namespace Tests\Feature\Ecommerce;

use App\Models\Coupon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CouponAndBuildApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_lists_active_coupons(): void
    {
        Coupon::factory()->create(['code' => 'A1', 'is_active' => true, 'expires_at' => now()->addDay()]);
        Coupon::factory()->create(['code' => 'OFF', 'is_active' => false]);

        $res = $this->getJson('/api/v1/coupons')->assertOk();
        $this->assertCount(1, $res->json());
        $this->assertSame('A1', $res->json()[0]['code']);
    }

    public function test_coupon_preview(): void
    {
        Coupon::factory()->create([
            'code' => 'MIN100',
            'min_subtotal_cents' => 100_000,
            'discount_cents' => 5_000,
            'is_active' => true,
            'expires_at' => now()->addDay(),
        ]);

        $this->getJson('/api/v1/coupons/MIN100/preview?subtotal_cents=150000')
            ->assertOk()
            ->assertJsonPath('eligible', true);

        $this->getJson('/api/v1/coupons/MIN100/preview?subtotal_cents=50000')
            ->assertOk()
            ->assertJsonPath('eligible', false);
    }

    public function test_build_validate_ok(): void
    {
        $this->postJson('/api/v1/build/validate', [
            'cpu_socket' => 'AM5',
            'motherboard_socket' => 'am5',
            'ram_type' => 'DDR5',
            'motherboard_ram_type' => 'ddr5',
            'psu_watts' => 750,
            'estimated_system_watts' => 400,
            'psu_headroom' => 0.2,
            'gpu_length_mm' => 300,
            'case_max_gpu_length_mm' => 350,
        ])->assertOk()->assertJsonPath('ok', true);
    }

    public function test_build_validate_socket_fail(): void
    {
        $this->postJson('/api/v1/build/validate', [
            'cpu_socket' => 'AM5',
            'motherboard_socket' => 'LGA1700',
        ])->assertStatus(422)->assertJsonPath('ok', false);
    }
}
