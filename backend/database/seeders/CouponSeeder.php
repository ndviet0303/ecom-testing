<?php

namespace Database\Seeders;

use App\Models\Coupon;
use Illuminate\Database\Seeder;

class CouponSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        Coupon::query()->updateOrCreate(
            ['code' => 'GIAM100K'],
            [
                'min_subtotal_cents' => 500000,
                'discount_cents' => 100000,
                'is_active' => true,
                'expires_at' => now()->addYear(),
                'max_uses' => 1000,
                'max_uses_per_user' => 1,
            ]
        );

        Coupon::query()->updateOrCreate(
            ['code' => 'CHAOBANMOI'],
            [
                'min_subtotal_cents' => 0,
                'discount_cents' => 50000,
                'is_active' => true,
                'expires_at' => now()->addYear(),
                'max_uses' => 5000,
            ]
        );
    }
}
