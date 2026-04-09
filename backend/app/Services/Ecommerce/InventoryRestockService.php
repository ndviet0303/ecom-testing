<?php

namespace App\Services\Ecommerce;

use App\Models\Inventory;
use App\Models\Order;
use App\Models\OrderItem;

class InventoryRestockService
{
    public function restockFromOrder(Order $order): void
    {
        $order->loadMissing('orderItems');
        foreach ($order->orderItems as $item) {
            $this->restockLine($item, $item->quantity);
        }
    }

    public function restockLine(OrderItem $item, int $quantity): void
    {
        if ($item->product_id === null || $quantity < 1) {
            return;
        }

        Inventory::query()->where('product_id', $item->product_id)->increment('on_hand', $quantity);
    }
}
