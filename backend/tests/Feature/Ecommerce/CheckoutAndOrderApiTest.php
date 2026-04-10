<?php

namespace Tests\Feature\Ecommerce;

use App\Models\Address;
use App\Models\Coupon;
use App\Models\Product;
use App\Models\ShippingZone;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class CheckoutAndOrderApiTest extends TestCase
{
    use RefreshDatabase;

    private function shippingZone(): ShippingZone
    {
        return ShippingZone::query()->create([
            'code' => 'TEST-Z',
            'name' => 'Test zone',
            'rate_per_kg_cents' => 5_000,
            'free_shipping_from_subtotal_cents' => 0,
            'is_active' => true,
        ]);
    }

    private function address(User $user): Address
    {
        return Address::query()->create([
            'user_id' => $user->id,
            'recipient_name' => 'Nguyen A',
            'phone' => '0900000000',
            'line1' => '1 Le Loi',
            'district' => 'Q1',
            'province' => 'HCM',
            'is_default' => true,
        ]);
    }

    public function test_checkout_creates_order_and_reduces_stock(): void
    {
        Notification::fake();

        $user = User::factory()->create();
        $zone = $this->shippingZone();
        $addr = $this->address($user);

        $product = Product::factory()->create(['base_price_cents' => 100_000]);
        $product->inventory->update(['on_hand' => 5, 'reserved' => 0]);

        Sanctum::actingAs($user);

        $this->postJson('/api/v1/cart/items', [
            'product_id' => $product->id,
            'quantity' => 2,
        ])->assertCreated();

        $res = $this->postJson('/api/v1/checkout', [
            'shipping_address_id' => $addr->id,
            'shipping_zone_id' => $zone->id,
            'weight_grams' => 2000,
            'tax_rate_basis_points' => 1000,
        ]);

        $res->assertCreated()
            ->assertJsonPath('order.subtotal_cents', 200_000)
            ->assertJsonPath('order.status', 'paid');

        $this->assertNotNull($res->json('order.order_number'));
        $this->assertIsArray($res->json('order.status_events'));

        $this->assertDatabaseCount('orders', 1);
        $this->assertDatabaseCount('order_items', 1);

        $product->inventory->refresh();
        $this->assertSame(3, $product->inventory->on_hand);
        $this->assertSame(0, $product->inventory->reserved);

        $this->getJson('/api/v1/cart')->assertOk()->assertJsonPath('subtotal_cents', 0);

        Notification::assertSentTo($user, \App\Notifications\OrderPlacedNotification::class);
    }

    public function test_customer_can_cancel_paid_order_and_stock_restored(): void
    {
        Notification::fake();

        $user = User::factory()->create();
        $zone = $this->shippingZone();
        $addr = $this->address($user);

        $product = Product::factory()->create(['base_price_cents' => 100_000]);
        $product->inventory->update(['on_hand' => 5, 'reserved' => 0]);

        Sanctum::actingAs($user);
        $this->postJson('/api/v1/cart/items', [
            'product_id' => $product->id,
            'quantity' => 2,
        ])->assertCreated();

        $checkout = $this->postJson('/api/v1/checkout', [
            'shipping_address_id' => $addr->id,
            'shipping_zone_id' => $zone->id,
            'weight_grams' => 1000,
            'tax_rate_basis_points' => 0,
        ])->assertCreated();

        $orderId = $checkout->json('order.id');
        $product->inventory->refresh();
        $this->assertSame(3, $product->inventory->on_hand);

        $this->postJson('/api/v1/orders/'.$orderId.'/cancel', [
            'reason' => 'Đổi ý',
        ])->assertOk()->assertJsonPath('status', 'cancelled');

        $product->inventory->refresh();
        $this->assertSame(5, $product->inventory->on_hand);
    }

    public function test_coupon_max_uses_blocks_after_limit(): void
    {
        Notification::fake();

        Coupon::factory()->create([
            'code' => 'ONCE',
            'min_subtotal_cents' => 0,
            'discount_cents' => 5_000,
            'is_active' => true,
            'expires_at' => now()->addDay(),
            'max_uses' => 1,
            'max_uses_per_user' => null,
        ]);

        $user1 = User::factory()->create();
        $user2 = User::factory()->create();
        $zone = $this->shippingZone();

        foreach ([$user1, $user2] as $user) {
            $addr = $this->address($user);
            $product = Product::factory()->create(['base_price_cents' => 100_000]);
            $product->inventory->update(['on_hand' => 10, 'reserved' => 0]);

            Sanctum::actingAs($user);
            $this->postJson('/api/v1/cart/items', [
                'product_id' => $product->id,
                'quantity' => 1,
            ])->assertCreated();

            $res = $this->postJson('/api/v1/checkout', [
                'shipping_address_id' => $addr->id,
                'shipping_zone_id' => $zone->id,
                'weight_grams' => 500,
                'tax_rate_basis_points' => 0,
                'coupon_code' => 'ONCE',
            ]);

            if ($user->is($user1)) {
                $res->assertCreated();
            } else {
                $res->assertUnprocessable()->assertJsonValidationErrors(['coupon_code']);
            }
        }
    }

    public function test_cannot_add_to_cart_beyond_on_hand(): void
    {
        $product = Product::factory()->create(['base_price_cents' => 10_000]);
        $product->inventory->update(['on_hand' => 1, 'reserved' => 0]);

        $this->postJson('/api/v1/cart/items', [
            'product_id' => $product->id,
            'quantity' => 3,
        ])->assertStatus(422);
    }

    public function test_checkout_with_coupon(): void
    {
        Notification::fake();

        $user = User::factory()->create();
        $zone = $this->shippingZone();
        $addr = $this->address($user);

        Coupon::factory()->create([
            'code' => 'SAVE10K',
            'min_subtotal_cents' => 50_000,
            'discount_cents' => 10_000,
            'is_active' => true,
            'expires_at' => now()->addDay(),
        ]);

        $product = Product::factory()->create(['base_price_cents' => 100_000]);
        $product->inventory->update(['on_hand' => 10, 'reserved' => 0]);

        Sanctum::actingAs($user);
        $this->postJson('/api/v1/cart/items', [
            'product_id' => $product->id,
            'quantity' => 1,
        ])->assertCreated();

        $res = $this->postJson('/api/v1/checkout', [
            'shipping_address_id' => $addr->id,
            'shipping_zone_id' => $zone->id,
            'weight_grams' => 1000,
            'tax_rate_basis_points' => 1000,
            'coupon_code' => 'save10k',
        ]);

        $res->assertCreated();
        $this->assertSame(10_000, $res->json('order.discount_cents'));
        $this->assertSame('save10k', strtolower((string) $res->json('order.coupon_code')));
    }

    public function test_checkout_with_pickup_requires_no_shipping_address_or_zone(): void
    {
        Notification::fake();

        $user = User::factory()->create();
        $product = Product::factory()->create(['base_price_cents' => 80_000]);
        $product->inventory->update(['on_hand' => 5, 'reserved' => 0]);

        Sanctum::actingAs($user);
        $this->postJson('/api/v1/cart/items', [
            'product_id' => $product->id,
            'quantity' => 1,
        ])->assertCreated();

        $res = $this->postJson('/api/v1/checkout', [
            'fulfillment_method' => 'pickup',
            'shipping_address_id' => null,
            'shipping_zone_id' => null,
            'weight_grams' => 0,
            'tax_rate_basis_points' => 0,
            'payment_method' => 'sepay_qr',
        ]);

        $res->assertCreated()
            ->assertJsonPath('order.shipping_cents', 0)
            ->assertJsonPath('order.shipping_address_id', null)
            ->assertJsonPath('order.shipping_zone_id', null)
            ->assertJsonPath('order.status', 'pending');
    }

    public function test_user_lists_and_cannot_see_other_users_order(): void
    {
        Notification::fake();

        $user = User::factory()->create();
        $other = User::factory()->create();
        $zone = $this->shippingZone();
        $addr = $this->address($user);

        $product = Product::factory()->create(['base_price_cents' => 50_000]);
        $product->inventory->update(['on_hand' => 5, 'reserved' => 0]);

        Sanctum::actingAs($user);
        $this->postJson('/api/v1/cart/items', [
            'product_id' => $product->id,
            'quantity' => 1,
        ])->assertCreated();

        $checkout = $this->postJson('/api/v1/checkout', [
            'shipping_address_id' => $addr->id,
            'shipping_zone_id' => $zone->id,
            'weight_grams' => 500,
            'tax_rate_basis_points' => 0,
        ])->assertCreated();

        $orderId = $checkout->json('order.id');

        $this->getJson('/api/v1/orders')->assertOk()->assertJsonCount(1, 'data');
        $this->getJson('/api/v1/orders/'.$orderId)->assertOk()->assertJsonPath('id', $orderId);

        Sanctum::actingAs($other);
        $this->getJson('/api/v1/orders/'.$orderId)->assertForbidden();
    }
}
