<?php

namespace App\Services\Ecommerce;

use App\Models\CartItem;
use App\Models\Inventory;
use Illuminate\Validation\ValidationException;

class InventoryReservationService
{
    /** Gọi trong transaction; khóa dòng inventory của sản phẩm. */
    public function syncProduct(int $productId): void
    {
        $inventory = Inventory::query()->where('product_id', $productId)->lockForUpdate()->first();

        if ($inventory === null) {
            throw ValidationException::withMessages([
                'stock' => ['Inventory record missing for product.'],
            ]);
        }

        $reserved = (int) CartItem::query()->where('product_id', $productId)->sum('quantity');

        if ($reserved > $inventory->on_hand) {
            throw ValidationException::withMessages([
                'stock' => ['Not enough stock available for cart quantity.'],
            ]);
        }

        $inventory->update(['reserved' => $reserved]);
    }

    /**
     * @param  list<int>  $productIds
     */
    public function syncProducts(array $productIds): void
    {
        foreach (array_unique($productIds) as $id) {
            $this->syncProduct((int) $id);
        }
    }
}
