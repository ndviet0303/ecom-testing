<?php

namespace App\Domain\Ecommerce\Order;

use App\Domain\Ecommerce\Exception\InvalidOrderTransitionException;

final class OrderStateMachine
{
    public function assertCanTransition(OrderStatus $from, OrderStatus $to): void
    {
        $allowed = match ($from) {
            OrderStatus::Pending => [OrderStatus::Paid, OrderStatus::Cancelled],
            OrderStatus::Paid => [OrderStatus::Packed, OrderStatus::Cancelled],
            OrderStatus::Packed => [OrderStatus::Shipped],
            OrderStatus::Shipped => [OrderStatus::Delivered],
            OrderStatus::Delivered => [],
            OrderStatus::Cancelled => [],
        };

        if (! in_array($to, $allowed, true)) {
            throw new InvalidOrderTransitionException(
                sprintf('Cannot transition order from %s to %s.', $from->value, $to->value)
            );
        }
    }

    public function canTransition(OrderStatus $from, OrderStatus $to): bool
    {
        try {
            $this->assertCanTransition($from, $to);

            return true;
        } catch (InvalidOrderTransitionException) {
            return false;
        }
    }
}
