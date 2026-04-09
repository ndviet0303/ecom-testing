<?php

namespace Tests\Feature\Ecommerce;

use App\Models\NewsletterSubscriber;
use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class EcomStandardFeaturesTest extends TestCase
{
    use RefreshDatabase;

    public function test_newsletter_subscribe_and_unsubscribe(): void
    {
        $this->postJson('/api/v1/newsletter/subscribe', [
            'email' => 'Fan@Example.com',
        ])->assertCreated();

        $this->assertDatabaseHas('newsletter_subscribers', [
            'email' => 'fan@example.com',
        ]);

        $this->postJson('/api/v1/newsletter/unsubscribe', [
            'email' => 'fan@example.com',
        ])->assertOk();

        $this->assertNotNull(
            NewsletterSubscriber::query()->where('email', 'fan@example.com')->value('unsubscribed_at')
        );
    }

    public function test_compare_and_recent_views(): void
    {
        $user = User::factory()->create();
        $p1 = Product::factory()->create();
        $p2 = Product::factory()->create();

        Sanctum::actingAs($user);

        $this->postJson('/api/v1/compare/'.$p1->id)->assertCreated();
        $this->postJson('/api/v1/compare/'.$p2->id)->assertCreated();
        $this->getJson('/api/v1/compare')->assertOk()->assertJsonCount(2);

        $this->postJson('/api/v1/recent-views/'.$p1->id)->assertCreated();
        $this->getJson('/api/v1/recent-views')->assertOk()->assertJsonCount(1);

        $this->deleteJson('/api/v1/compare/'.$p1->id)->assertOk();
        $this->getJson('/api/v1/compare')->assertOk()->assertJsonCount(1);
    }

    public function test_staff_fulfillment_and_low_stock_report(): void
    {
        $staff = User::factory()->staff()->create();
        $product = Product::factory()->create();
        $product->inventory->update(['on_hand' => 2, 'reserved' => 0, 'low_stock_threshold' => 5]);

        $buyer = User::factory()->create();
        $order = Order::query()->create([
            'user_id' => $buyer->id,
            'status' => 'paid',
            'subtotal_cents' => 10_000,
            'discount_cents' => 0,
            'shipping_cents' => 0,
            'tax_cents' => 0,
            'total_cents' => 10_000,
            'coupon_code' => null,
            'weight_grams' => 0,
            'shipping_address_id' => null,
            'shipping_address_snapshot' => null,
            'shipping_zone_id' => null,
        ]);
        $order->update([
            'order_number' => sprintf('ORD-%s-%08d', now()->format('Ymd'), $order->id),
        ]);

        Sanctum::actingAs($staff);
        $this->patchJson('/api/v1/admin/orders/'.$order->id.'/fulfillment', [
            'tracking_number' => 'VN123456',
            'tracking_carrier' => 'GHN',
            'internal_note' => 'Gói cẩn thận',
        ])->assertOk()
            ->assertJsonPath('tracking_number', 'VN123456')
            ->assertJsonPath('tracking_carrier', 'GHN');

        $this->getJson('/api/v1/admin/inventory/low-stock')->assertOk();
        $payload = $this->getJson('/api/v1/admin/inventory/low-stock')->json();
        $this->assertNotEmpty($payload);
    }
}
