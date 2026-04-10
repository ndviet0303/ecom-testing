<?php

namespace Tests\Feature\Ecommerce;

use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class CartApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_cart_empty_without_token(): void
    {
        $this->getJson('/api/v1/cart')
            ->assertOk()
            ->assertJsonPath('cart_id', null)
            ->assertJsonPath('subtotal_cents', 0);
    }

    public function test_guest_add_item_returns_cart_token_and_persists(): void
    {
        $product = Product::factory()->create([
            'base_price_cents' => 99_000,
            'sale_price_cents' => null,
        ]);

        $first = $this->postJson('/api/v1/cart/items', [
            'product_id' => $product->id,
            'quantity' => 2,
        ]);

        $first->assertCreated();
        $token = $first->headers->get('X-Cart-Token');
        $this->assertNotEmpty($token);
        $this->assertSame(198_000, $first->json('subtotal_cents'));

        $second = $this->withHeader('X-Cart-Token', $token)->postJson('/api/v1/cart/items', [
            'product_id' => $product->id,
            'quantity' => 1,
        ]);

        $second->assertCreated();
        $this->assertSame(297_000, $second->json('subtotal_cents'));

        $this->withHeader('X-Cart-Token', $token)
            ->getJson('/api/v1/cart')
            ->assertOk()
            ->assertJsonPath('subtotal_cents', 297_000)
            ->assertJsonPath('items.0.quantity', 3);
    }

    public function test_guest_update_and_remove_line(): void
    {
        $product = Product::factory()->create(['base_price_cents' => 10_000]);

        $r = $this->postJson('/api/v1/cart/items', [
            'product_id' => $product->id,
            'quantity' => 2,
        ]);
        $token = $r->headers->get('X-Cart-Token');

        $this->withHeader('X-Cart-Token', $token)
            ->putJson('/api/v1/cart/items/'.$product->id, ['quantity' => 3])
            ->assertOk()
            ->assertJsonPath('subtotal_cents', 30_000);

        $this->withHeader('X-Cart-Token', $token)
            ->deleteJson('/api/v1/cart/items/'.$product->id)
            ->assertOk()
            ->assertJsonPath('subtotal_cents', 0);
    }

    public function test_merge_guest_cart_into_user(): void
    {
        $product = Product::factory()->create(['base_price_cents' => 5_000]);
        $guest = $this->postJson('/api/v1/cart/items', [
            'product_id' => $product->id,
            'quantity' => 2,
        ]);
        $token = $guest->headers->get('X-Cart-Token');

        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $this->postJson('/api/v1/cart/merge', ['guest_token' => $token])
            ->assertOk();

        $this->getJson('/api/v1/cart')->assertOk()->assertJsonPath('subtotal_cents', 10_000);
    }

    public function test_guest_bulk_add_increments_existing_lines(): void
    {
        $product = Product::factory()->create([
            'base_price_cents' => 25_000,
        ]);

        $first = $this->postJson('/api/v1/cart/items/bulk', [
            'items' => [
                ['product_id' => $product->id, 'quantity' => 1],
            ],
        ]);

        $first->assertCreated();
        $token = $first->headers->get('X-Cart-Token');
        $this->assertNotEmpty($token);
        $this->assertSame(25_000, $first->json('subtotal_cents'));

        $second = $this->withHeader('X-Cart-Token', $token)->postJson('/api/v1/cart/items/bulk', [
            'items' => [
                ['product_id' => $product->id, 'quantity' => 1],
            ],
        ]);

        $second->assertCreated();
        $this->assertSame(50_000, $second->json('subtotal_cents'));

        $this->withHeader('X-Cart-Token', $token)
            ->getJson('/api/v1/cart')
            ->assertOk()
            ->assertJsonPath('subtotal_cents', 50_000)
            ->assertJsonPath('items.0.quantity', 2);
    }
}
