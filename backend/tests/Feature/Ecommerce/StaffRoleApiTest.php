<?php

namespace Tests\Feature\Ecommerce;

use App\Models\Address;
use App\Models\Product;
use App\Models\ShippingZone;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class StaffRoleApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_staff_can_transition_order_but_cannot_create_product(): void
    {
        Notification::fake();

        $staff = User::factory()->staff()->create();
        $user = User::factory()->create();

        $zone = ShippingZone::query()->create([
            'code' => 'Z-ST', 'name' => 'Z', 'rate_per_kg_cents' => 1000,
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

        Sanctum::actingAs($staff);
        $this->patchJson('/api/v1/admin/orders/'.$orderId.'/status', [
            'status' => 'packed',
        ])->assertOk()->assertJsonPath('status', 'packed');

        $this->postJson('/api/v1/admin/products', [
            'sku' => 'NOPE',
            'name' => 'X',
            'category' => 'cpu',
            'base_price_cents' => 1000,
        ])->assertForbidden();
    }
}
