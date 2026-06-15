<?php

namespace Tests\Feature;

use App\Models\Address;
use App\Models\Product;
use App\Models\ShippingZone;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class VietQrIntegrationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'sepay.account_number' => '83403032005',
            'sepay.bank_code' => 'TPBank',
            'sepay.transfer_content_prefix' => 'TTECOMDZ',
            'sepay.secret_key' => 'test-sepay-secret',
            'sepay.verify_account_number' => false,
        ]);
    }

    public function test_checkout_sepay_qr_creates_pending_order_and_qr_endpoint(): void
    {
        Notification::fake();

        $user = User::factory()->create();
        $zone = ShippingZone::query()->create([
            'code' => 'Z-SP', 'name' => 'Z', 'rate_per_kg_cents' => 1000,
            'free_shipping_from_subtotal_cents' => 0, 'is_active' => true,
        ]);
        $addr = Address::query()->create([
            'user_id' => $user->id, 'country' => 'VN',
            'recipient_name' => 'A', 'phone' => '0900000000',
            'line1' => 'x', 'district' => 'd', 'province' => 'p', 'is_default' => true,
        ]);
        $product = Product::factory()->create(['base_price_cents' => 100_000]);
        $product->inventory->update(['on_hand' => 5, 'reserved' => 0]);

        Sanctum::actingAs($user);
        $this->postJson('/api/v1/cart/items', [
            'product_id' => $product->id,
            'quantity' => 1,
        ])->assertCreated();

        $res = $this->postJson('/api/v1/checkout', [
            'shipping_address_id' => $addr->id,
            'shipping_zone_id' => $zone->id,
            'weight_grams' => 100,
            'tax_rate_basis_points' => 0,
            'payment_method' => 'sepay_qr',
        ]);

        $res->assertCreated()->assertJsonPath('order.status', 'pending');
        $orderId = $res->json('order.id');
        $total = (int) $res->json('order.total_cents');

        $qr = $this->getJson('/api/v1/orders/'.$orderId.'/vietqr-qr')->assertOk();
        $this->assertStringContainsString('qr.sepay.vn', $qr->json('qr_image_url'));
        $this->assertStringContainsString('TTECOMDZ', $qr->json('qr_image_url'));
        $this->assertSame($total, $qr->json('amount'));
        $this->assertStringContainsString((string) $orderId, $qr->json('transfer_content'));

        $this->postJson('/api/v1/webhooks/vietqr', [
            'notification_type' => 'ORDER_PAID',
            'order' => [
                'order_id' => (string) $orderId,
                'order_amount' => (string) ($total / 100), // SePay Gateway often sends in major units or string
            ],
            'transaction' => [
                'id' => 'TX12345',
                'transaction_id' => 'SEPAY_99001',
                'transaction_amount' => (string) $total,
                'transaction_description' => 'TTECOMDZ '.(string) $orderId,
                'reference_number' => 'REF.1',
                'transaction_type' => 'PAYMENT',
            ],
        ], [
            'X-Secret-Key' => 'test-sepay-secret',
        ])->assertOk()->assertJsonPath('success', true);

        $this->getJson('/api/v1/orders/'.$orderId)->assertOk()->assertJsonPath('status', 'paid');
    }

    public function test_webhook_rejects_invalid_secret_key(): void
    {
        config(['sepay.secret_key' => 'secret']);

        $this->postJson('/api/v1/webhooks/vietqr', ['id' => 1], [
            'X-Secret-Key' => 'wrong',
        ])->assertUnauthorized();
    }
}
