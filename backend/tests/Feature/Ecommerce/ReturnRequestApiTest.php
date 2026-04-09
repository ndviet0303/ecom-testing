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

class ReturnRequestApiTest extends TestCase
{
    use RefreshDatabase;

    private function placePaidOrder(): array
    {
        Notification::fake();

        $user = User::factory()->create();
        $zone = ShippingZone::query()->create([
            'code' => 'Z-RMA', 'name' => 'Z', 'rate_per_kg_cents' => 1000,
            'free_shipping_from_subtotal_cents' => 0, 'is_active' => true,
        ]);
        $addr = Address::query()->create([
            'user_id' => $user->id, 'recipient_name' => 'A', 'phone' => '0900000000',
            'line1' => 'x', 'district' => 'd', 'province' => 'p', 'is_default' => true,
        ]);
        $product = Product::factory()->create(['base_price_cents' => 15_000]);
        $product->inventory->update(['on_hand' => 5, 'reserved' => 0]);

        Sanctum::actingAs($user);
        $this->postJson('/api/v1/cart/items', [
            'product_id' => $product->id,
            'quantity' => 2,
        ])->assertCreated();

        $co = $this->postJson('/api/v1/checkout', [
            'shipping_address_id' => $addr->id,
            'shipping_zone_id' => $zone->id,
            'weight_grams' => 100,
            'tax_rate_basis_points' => 0,
        ])->assertCreated();

        $orderId = $co->json('order.id');
        $itemId = $co->json('order.order_items.0.id');

        return [$user, (int) $orderId, (int) $itemId];
    }

    private function advanceToDelivered(User $staffOrAdmin, int $orderId): void
    {
        Sanctum::actingAs($staffOrAdmin);
        foreach (['packed', 'shipped', 'delivered'] as $status) {
            $this->patchJson('/api/v1/admin/orders/'.$orderId.'/status', [
                'status' => $status,
            ])->assertOk();
        }
    }

    public function test_user_cannot_request_return_when_order_only_paid(): void
    {
        [$user, $orderId, $itemId] = $this->placePaidOrder();

        Sanctum::actingAs($user);
        $this->postJson('/api/v1/return-requests', [
            'order_item_id' => $itemId,
            'quantity' => 1,
        ])->assertUnprocessable();

        $this->assertDatabaseMissing('return_requests', ['order_id' => $orderId]);
    }

    public function test_user_can_create_return_after_delivered_and_staff_can_approve(): void
    {
        [$user, $orderId, $itemId] = $this->placePaidOrder();
        $staff = User::factory()->staff()->create();

        $this->advanceToDelivered($staff, $orderId);

        Sanctum::actingAs($user);
        $res = $this->postJson('/api/v1/return-requests', [
            'order_item_id' => $itemId,
            'quantity' => 1,
            'reason' => 'Lỗi linh kiện',
        ])->assertCreated();

        $rrId = $res->json('id');
        $this->assertDatabaseHas('return_requests', [
            'id' => $rrId,
            'order_id' => $orderId,
            'status' => 'pending',
        ]);

        Sanctum::actingAs($staff);
        $this->patchJson('/api/v1/admin/return-requests/'.$rrId, [
            'status' => 'approved',
            'staff_note' => 'OK',
        ])->assertOk()->assertJsonPath('status', 'approved');

        $this->assertDatabaseHas('audit_logs', [
            'user_id' => $staff->id,
            'action' => 'return_request.transition',
            'subject_id' => $rrId,
        ]);

        $this->assertDatabaseHas('audit_logs', [
            'user_id' => $user->id,
            'action' => 'return_request.created',
            'subject_id' => $rrId,
        ]);
    }

    public function test_staff_cannot_skip_state_machine_to_refunded(): void
    {
        [$user, $orderId, $itemId] = $this->placePaidOrder();
        $staff = User::factory()->staff()->create();
        $this->advanceToDelivered($staff, $orderId);

        Sanctum::actingAs($user);
        $rrId = $this->postJson('/api/v1/return-requests', [
            'order_item_id' => $itemId,
            'quantity' => 1,
        ])->assertCreated()->json('id');

        Sanctum::actingAs($staff);
        $this->patchJson('/api/v1/admin/return-requests/'.$rrId, [
            'status' => 'refunded',
        ])->assertUnprocessable()->assertJsonValidationErrors(['status']);
    }

    public function test_user_cannot_view_another_users_return_request(): void
    {
        [$user, $orderId, $itemId] = $this->placePaidOrder();
        $staff = User::factory()->staff()->create();
        $this->advanceToDelivered($staff, $orderId);

        Sanctum::actingAs($user);
        $rrId = $this->postJson('/api/v1/return-requests', [
            'order_item_id' => $itemId,
            'quantity' => 1,
        ])->assertCreated()->json('id');

        $other = User::factory()->create();
        Sanctum::actingAs($other);
        $this->getJson('/api/v1/return-requests/'.$rrId)->assertForbidden();
    }

    public function test_happy_path_refund_after_received(): void
    {
        [$user, $orderId, $itemId] = $this->placePaidOrder();
        $staff = User::factory()->staff()->create();
        $this->advanceToDelivered($staff, $orderId);

        $productId = \App\Models\OrderItem::query()->findOrFail($itemId)->product_id;
        $inv = \App\Models\Inventory::query()->where('product_id', $productId)->firstOrFail();
        $onHandAfterSale = $inv->on_hand;

        Sanctum::actingAs($user);
        $rrId = $this->postJson('/api/v1/return-requests', [
            'order_item_id' => $itemId,
            'quantity' => 1,
        ])->assertCreated()->json('id');

        Sanctum::actingAs($staff);
        $this->patchJson('/api/v1/admin/return-requests/'.$rrId, ['status' => 'approved'])->assertOk();
        $this->patchJson('/api/v1/admin/return-requests/'.$rrId, ['status' => 'received'])->assertOk();
        $this->patchJson('/api/v1/admin/return-requests/'.$rrId, ['status' => 'refunded'])->assertOk();

        $this->assertDatabaseHas('return_requests', [
            'id' => $rrId,
            'status' => 'refunded',
        ]);

        $inv->refresh();
        $this->assertSame($onHandAfterSale + 1, $inv->on_hand);
    }
}
