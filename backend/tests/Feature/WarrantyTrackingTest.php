<?php

namespace Tests\Feature;

use App\Models\Address;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\ShippingZone;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class WarrantyTrackingTest extends TestCase
{
    use RefreshDatabase;

    public function test_assigning_serial_number_calculates_warranty_expiration(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        
        $product = Product::factory()->create([
            'name' => 'Ryzen 7 7800X3D',
            'warranty_months' => 36,
        ]);
        
        $order = Order::factory()->create(['status' => 'paid']);
        $item = OrderItem::query()->create([
            'order_id' => $order->id,
            'product_id' => $product->id,
            'sku' => $product->sku,
            'name' => $product->name,
            'quantity' => 1,
            'unit_price_cents' => 100000,
        ]);

        Sanctum::actingAs($admin);

        $res = $this->patchJson("/api/v1/admin/orders/{$order->id}/fulfillment", [
            'items' => [
                [
                    'id' => $item->id,
                    'serial_number' => 'SN-123456789',
                ]
            ]
        ]);

        $res->assertOk();
        
        $item->refresh();
        $this->assertEquals('SN-123456789', $item->serial_number);
        $this->assertNotNull($item->warranty_expires_at);
        
        // Kiểm tra xem ngày hết hạn có khớp với 36 tháng sau không
        $expected = now()->addMonths(36);
        $this->assertTrue($item->warranty_expires_at->isSameDay($expected));
        $this->assertTrue($item->is_under_warranty);
    }

    public function test_warranty_not_calculated_if_no_warranty_months_defined(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        
        $product = Product::factory()->create([
            'warranty_months' => 0,
        ]);
        
        $order = Order::factory()->create(['status' => 'paid']);
        $item = OrderItem::query()->create([
            'order_id' => $order->id,
            'product_id' => $product->id,
            'sku' => $product->sku,
            'name' => $product->name,
            'quantity' => 1,
            'unit_price_cents' => 100000,
        ]);

        Sanctum::actingAs($admin);

        $this->patchJson("/api/v1/admin/orders/{$order->id}/fulfillment", [
            'items' => [
                [
                    'id' => $item->id,
                    'serial_number' => 'SN-NO-WARRANTY',
                ]
            ]
        ]);

        $item->refresh();
        $this->assertEquals('SN-NO-WARRANTY', $item->serial_number);
        $this->assertNull($item->warranty_expires_at);
        $this->assertFalse($item->is_under_warranty);
    }
}
