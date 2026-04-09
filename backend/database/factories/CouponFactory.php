<?php

namespace Database\Factories;

use App\Models\Coupon;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Coupon>
 */
class CouponFactory extends Factory
{
    protected $model = Coupon::class;

    public function definition(): array
    {
        return [
            'code' => strtoupper(fake()->unique()->bothify('SAVE##')),
            'min_subtotal_cents' => 0,
            'discount_cents' => 10_000,
            'is_active' => true,
            'expires_at' => now()->addMonth(),
        ];
    }
}
