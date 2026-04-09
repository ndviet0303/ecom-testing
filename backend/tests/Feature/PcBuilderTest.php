<?php

namespace Tests\Feature;

use App\Models\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PcBuilderTest extends TestCase
{
    use RefreshDatabase;

    public function test_validate_compatible_build(): void
    {
        $cpu = Product::factory()->create([
            'category' => 'CPU',
            'specs' => ['socket' => 'AM5', 'tdp' => 120]
        ]);
        
        $mb = Product::factory()->create([
            'category' => 'Motherboard',
            'specs' => ['socket' => 'AM5', 'ram_type' => 'DDR5']
        ]);
        
        $ram = Product::factory()->create([
            'category' => 'RAM',
            'specs' => ['ram_type' => 'DDR5']
        ]);
        
        $psu = Product::factory()->create([
            'category' => 'PSU',
            'specs' => ['wattage' => 750]
        ]);

        $res = $this->postJson('/api/v1/pc-builder/validate', [
            'product_ids' => [$cpu->id, $mb->id, $ram->id, $psu->id]
        ]);

        $res->assertOk()
            ->assertJson([
                'valid' => true,
                'errors' => [],
                'estimated_wattage' => 170, // 50 base + 120 CPU
            ]);
    }

    public function test_validate_incompatible_sockets(): void
    {
        $cpu = Product::factory()->create([
            'category' => 'CPU',
            'specs' => ['socket' => 'LGA1700']
        ]);
        
        $mb = Product::factory()->create([
            'category' => 'Motherboard',
            'specs' => ['socket' => 'AM5']
        ]);

        $res = $this->postJson('/api/v1/pc-builder/validate', [
            'product_ids' => [$cpu->id, $mb->id]
        ]);

        $res->assertOk()
            ->assertJson([
                'valid' => false,
                'errors' => ['CPU socket does not match motherboard.']
            ]);
    }

    public function test_validate_incompatible_ram(): void
    {
        $mb = Product::factory()->create([
            'category' => 'Motherboard',
            'specs' => ['ram_type' => 'DDR5']
        ]);
        
        $ram = Product::factory()->create([
            'category' => 'RAM',
            'specs' => ['ram_type' => 'DDR4']
        ]);

        $res = $this->postJson('/api/v1/pc-builder/validate', [
            'product_ids' => [$mb->id, $ram->id]
        ]);

        $res->assertOk()
            ->assertJson([
                'valid' => false,
                'errors' => ['RAM type is not supported by this motherboard.']
            ]);
    }

    public function test_validate_insufficient_psu(): void
    {
        $cpu = Product::factory()->create([
            'category' => 'CPU',
            'specs' => ['tdp' => 250]
        ]);
        
        $gpu = Product::factory()->create([
            'category' => 'GPU',
            'specs' => ['tbp' => 450]
        ]);
        
        $psu = Product::factory()->create([
            'category' => 'PSU',
            'specs' => ['wattage' => 500] // 50+250+450 = 750 total. 500 is not enough.
        ]);

        $res = $this->postJson('/api/v1/pc-builder/validate', [
            'product_ids' => [$cpu->id, $gpu->id, $psu->id]
        ]);

        $res->assertOk()
            ->assertJson([
                'valid' => false,
                'errors' => ['PSU wattage is insufficient for estimated load with headroom.']
            ]);
    }
}
