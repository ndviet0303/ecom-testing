<?php

namespace Tests\Feature\Ecommerce;

use App\Models\Address;
use App\Models\Product;
use App\Models\ShippingZone;
use App\Models\User;
use App\Services\WebhookIdempotencyService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ExtendedEcommerceFeaturesTest extends TestCase
{
    use RefreshDatabase;

    public function test_webhook_idempotency_store(): void
    {
        $svc = app(WebhookIdempotencyService::class);

        $this->assertTrue($svc->tryConsume('vnpay', 'evt-1', 'abc'));
        $this->assertFalse($svc->tryConsume('vnpay', 'evt-1', 'abc'));
    }

    public function test_admin_transitions_order_and_writes_audit(): void
    {
        Notification::fake();

        $admin = User::factory()->admin()->create();
        $user = User::factory()->create();

        $zone = ShippingZone::query()->create([
            'code' => 'Z1', 'name' => 'Z', 'rate_per_kg_cents' => 1000,
            'free_shipping_from_subtotal_cents' => 0, 'is_active' => true,
        ]);
        $addr = Address::query()->create([
            'user_id' => $user->id, 'recipient_name' => 'A', 'phone' => '0900000000',
            'line1' => 'x', 'district' => 'd', 'province' => 'p', 'is_default' => true,
        ]);

        $product = Product::factory()->create(['base_price_cents' => 10_000]);
        $product->inventory->update(['on_hand' => 5, 'reserved' => 0]);

        Sanctum::actingAs($user);
        $this->postJson('/api/v1/cart/items', [
            'product_id' => $product->id,
            'quantity' => 1,
        ])->assertCreated();

        $co = $this->postJson('/api/v1/checkout', [
            'shipping_address_id' => $addr->id,
            'shipping_zone_id' => $zone->id,
            'weight_grams' => 500,
            'tax_rate_basis_points' => 0,
        ])->assertCreated();

        $orderId = $co->json('order.id');

        Sanctum::actingAs($admin);
        $this->patchJson('/api/v1/admin/orders/'.$orderId.'/status', [
            'status' => 'packed',
        ])->assertOk()->assertJsonPath('status', 'packed');

        $this->assertDatabaseHas('audit_logs', [
            'user_id' => $admin->id,
            'action' => 'order.transition',
        ]);
    }

    public function test_wishlist_flow(): void
    {
        $user = User::factory()->create();
        $product = Product::factory()->create();

        Sanctum::actingAs($user);

        $this->postJson('/api/v1/wishlist/'.$product->id)->assertCreated();
        $this->getJson('/api/v1/wishlist')->assertOk()->assertJsonCount(1);
        $this->deleteJson('/api/v1/wishlist/'.$product->id)->assertOk();
        $this->getJson('/api/v1/wishlist')->assertOk()->assertJsonCount(0);
    }

    public function test_review_requires_purchase(): void
    {
        Notification::fake();

        $user = User::factory()->create();
        $zone = ShippingZone::query()->create([
            'code' => 'Z2', 'name' => 'Z', 'rate_per_kg_cents' => 1000,
            'free_shipping_from_subtotal_cents' => 0, 'is_active' => true,
        ]);
        $addr = Address::query()->create([
            'user_id' => $user->id, 'recipient_name' => 'A', 'phone' => '0900000000',
            'line1' => 'x', 'district' => 'd', 'province' => 'p', 'is_default' => true,
        ]);

        $product = Product::factory()->create(['base_price_cents' => 20_000]);
        $product->inventory->update(['on_hand' => 3, 'reserved' => 0]);

        Sanctum::actingAs($user);
        $this->postJson('/api/v1/products/'.$product->id.'/reviews', [
            'rating' => 5,
        ])->assertStatus(422);

        $this->postJson('/api/v1/cart/items', [
            'product_id' => $product->id,
            'quantity' => 1,
        ])->assertCreated();

        $co = $this->postJson('/api/v1/checkout', [
            'shipping_address_id' => $addr->id,
            'shipping_zone_id' => $zone->id,
            'weight_grams' => 100,
            'tax_rate_basis_points' => 0,
        ])->assertCreated();

        $this->postJson('/api/v1/products/'.$product->id.'/reviews', [
            'rating' => 5,
            'body' => 'Good',
            'order_id' => $co->json('order.id'),
        ])->assertCreated();

        $this->getJson('/api/v1/products/'.$product->id.'/reviews')->assertOk()->assertJsonCount(1, 'data');
    }
}
