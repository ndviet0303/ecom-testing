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

class OrderTransitionService
{
    public function __construct(
        private readonly OrderStateMachine $stateMachine,
        private readonly AuditLogger $auditLogger,
        private readonly OrderStatusEventRecorder $orderStatusEventRecorder,
    ) {}

    public function transition(Order $order, OrderStatus $to, User $admin, ?string $ip = null): Order
    {
        return DB::transaction(function () use ($order, $to, $admin, $ip): Order {
            $order = Order::query()->whereKey($order->id)->lockForUpdate()->firstOrFail();
            $from = OrderStatus::from($order->status);

            try {
                $this->stateMachine->assertCanTransition($from, $to);
            } catch (InvalidOrderTransitionException $e) {
                throw ValidationException::withMessages([
                    'status' => [$e->getMessage()],
                ]);
            }

            $order->update(['status' => $to->value]);

            $this->orderStatusEventRecorder->record(
                $order,
                $from->value,
                $to->value,
                'staff',
                $admin->id,
                null
            );

            $this->auditLogger->log($admin, 'order.transition', $order, [
                'from' => $from->value,
                'to' => $to->value,
            ], $ip);

            return $order->fresh();
        });
    }
}
