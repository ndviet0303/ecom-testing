<?php

namespace App\Services\Ecommerce;

use App\Domain\Ecommerce\Exception\InvalidOrderTransitionException;
use App\Domain\Ecommerce\Order\OrderStateMachine;
use App\Domain\Ecommerce\Order\OrderStatus;
use App\Models\Order;
use App\Models\User;
use App\Services\AuditLogger;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class OrderCancellationService
{
    public function __construct(
        private readonly OrderStateMachine $stateMachine,
        private readonly AuditLogger $auditLogger,
        private readonly OrderStatusEventRecorder $orderStatusEventRecorder,
        private readonly InventoryRestockService $inventoryRestockService,
    ) {}

    public function cancelByCustomer(Order $order, User $user, ?string $reason, ?string $ip): Order
    {
        return DB::transaction(function () use ($order, $user, $reason, $ip): Order {
            $order = Order::query()->whereKey($order->id)->lockForUpdate()->firstOrFail();

            if ($order->user_id !== $user->id) {
                abort(403);
            }

            $from = OrderStatus::from($order->status);

            try {
                $this->stateMachine->assertCanTransition($from, OrderStatus::Cancelled);
            } catch (InvalidOrderTransitionException $e) {
                throw ValidationException::withMessages([
                    'order' => [$e->getMessage()],
                ]);
            }

            $order->update([
                'status' => OrderStatus::Cancelled->value,
                'cancelled_at' => now(),
                'cancel_reason' => $reason,
            ]);

            $this->inventoryRestockService->restockFromOrder($order);

            $this->orderStatusEventRecorder->record(
                $order,
                $from->value,
                OrderStatus::Cancelled->value,
                'user',
                $user->id,
                $reason !== null && $reason !== '' ? ['cancel_reason' => $reason] : null,
            );

            $this->auditLogger->log($user, 'order.cancelled', $order, [
                'from' => $from->value,
            ], $ip);

            return $order->fresh();
        });
    }
}
