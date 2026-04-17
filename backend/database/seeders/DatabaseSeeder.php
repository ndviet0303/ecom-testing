<?php

namespace Database\Seeders;

use App\Models\ShippingZone;
use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        ShippingZone::query()->firstOrCreate(
            ['code' => 'VN-STD'],
            [
                'name' => 'Nội địa (mặc định)',
                'rate_per_kg_cents' => 5_000,
                'free_shipping_from_subtotal_cents' => 0,
                'is_active' => true,
            ]
        );

        User::factory()->create([
            'name' => 'Test User',
            'email' => 'test@example.com',
        ]);
    }
}
