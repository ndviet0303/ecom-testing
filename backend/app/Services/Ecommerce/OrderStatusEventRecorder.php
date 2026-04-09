<?php

namespace App\Services\Ecommerce;

use App\Models\Order;
use App\Models\OrderStatusEvent;

class OrderStatusEventRecorder
{
    public function record(
        Order $order,
        ?string $fromStatus,
        string $toStatus,
        string $actorType,
        ?int $actorId = null,
        ?array $meta = null,
    ): void {
        OrderStatusEvent::query()->create([
            'order_id' => $order->id,
            'from_status' => $fromStatus,
            'to_status' => $toStatus,
            'actor_type' => $actorType,
            'actor_id' => $actorId,
            'meta' => $meta,
            'created_at' => now(),
        ]);
    }
}
