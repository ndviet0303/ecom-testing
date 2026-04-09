<?php

namespace Tests\Feature\Ecommerce;

use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ProductApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_can_list_and_show_products(): void
    {
        $products = Product::factory()->count(2)->create();

        $this->getJson('/api/v1/products')->assertOk()->assertJsonStructure([
            'data' => [
                '*' => ['id', 'sku', 'name', 'category', 'base_price_cents'],
            ],
        ]);

        $this->getJson('/api/v1/products/'.$products->first()->id)
            ->assertOk()
            ->assertJsonPath('sku', $products->first()->sku);
    }

    public function test_list_filters_by_category_and_search(): void
    {
        Product::factory()->create(['category' => 'cpu', 'name' => 'Ryzen 9']);
        Product::factory()->create(['category' => 'ram', 'name' => 'DDR5 Kit']);

        $this->getJson('/api/v1/products?category=cpu')->assertOk()->assertJsonCount(1, 'data');
        $this->getJson('/api/v1/products?q=Ryzen')->assertOk()->assertJsonCount(1, 'data');
    }

    public function test_guest_cannot_create_product(): void
    {
        $this->postJson('/api/v1/admin/products', [
            'sku' => 'NEW-1',
            'name' => 'X',
            'category' => 'cpu',
            'base_price_cents' => 1000,
        ])->assertUnauthorized();
    }

    public function test_non_admin_cannot_manage_products(): void
    {
        Sanctum::actingAs(User::factory()->create(['role' => User::ROLE_CUSTOMER]));

        $this->postJson('/api/v1/admin/products', [
            'sku' => 'X',
            'name' => 'X',
            'category' => 'cpu',
            'base_price_cents' => 1000,
        ])->assertForbidden();
    }

    public function test_admin_can_crud_product(): void
    {
        Sanctum::actingAs(User::factory()->admin()->create());

        $create = $this->postJson('/api/v1/admin/products', [
            'sku' => 'CRUD-1',
            'name' => 'Test CPU',
            'category' => 'cpu',
            'brand' => 'AMD',
            'base_price_cents' => 250_000,
            'sale_price_cents' => 200_000,
            'initial_on_hand' => 10,
        ]);

        $create->assertCreated()->assertJsonPath('inventory.on_hand', 10);
        $id = $create->json('id');

        $this->putJson('/api/v1/admin/products/'.$id, [
            'name' => 'Test CPU Pro',
        ])->assertOk()->assertJsonPath('name', 'Test CPU Pro');

        $this->deleteJson('/api/v1/admin/products/'.$id)->assertOk();

        $this->assertSoftDeleted('products', ['id' => $id]);
    }

    public function test_store_validates_price_rules(): void
    {
        Sanctum::actingAs(User::factory()->admin()->create());

        $this->postJson('/api/v1/admin/products', [
            'sku' => 'BAD',
            'name' => 'X',
            'category' => 'cpu',
            'base_price_cents' => 1000,
            'sale_price_cents' => 2000,
        ])->assertStatus(422);
    }
}
