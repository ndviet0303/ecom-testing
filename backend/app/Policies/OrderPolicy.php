<?php

namespace App\Policies;

use App\Domain\Ecommerce\Order\OrderStatus;
use App\Models\Order;
use App\Models\User;

class OrderPolicy
{
    public function view(User $user, Order $order): bool
    {
        return $order->user_id === $user->id;
    }

    /** Hủy khi đơn còn pending hoặc paid (chưa đóng gói). */
    public function cancel(User $user, Order $order): bool
    {
        if ($order->user_id !== $user->id) {
            return false;
        }

        $status = OrderStatus::tryFrom($order->status);

        return $status === OrderStatus::Pending || $status === OrderStatus::Paid;
    }
}
