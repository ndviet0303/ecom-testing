<?php

namespace Database\Factories;

use App\Models\Order;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Order>
 */
class OrderFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'order_number' => 'ORD-'.fake()->unique()->numerify('##########'),
            'status' => 'pending',
            'subtotal_cents' => 100000,
            'discount_cents' => 0,
            'shipping_cents' => 0,
            'tax_cents' => 0,
            'total_cents' => 100000,
        ];
    }
}
