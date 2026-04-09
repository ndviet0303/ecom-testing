<?php

namespace Database\Factories;

use App\Models\Inventory;
use App\Models\Product;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Product>
 */
class ProductFactory extends Factory
{
    protected $model = Product::class;

    public function definition(): array
    {
        return [
            'sku' => fake()->unique()->bothify('SKU-####'),
            'name' => fake()->words(3, true),
            'description' => fake()->optional()->sentence(),
            'category' => fake()->randomElement(['cpu', 'mainboard', 'ram', 'gpu', 'psu', 'case']),
            'brand' => fake()->company(),
            'base_price_cents' => fake()->numberBetween(100_00, 5000_00),
            'sale_price_cents' => null,
            'specs' => null,
        ];
    }

    public function configure(): static
    {
        return $this->afterCreating(function (Product $product): void {
            Inventory::query()->firstOrCreate(
                ['product_id' => $product->id],
                ['on_hand' => 50, 'reserved' => 0, 'low_stock_threshold' => 0]
            );
        });
    }
}
