<?php

namespace App\Domain\Ecommerce\Order;

enum OrderStatus: string
{
    case Pending = 'pending';
    case Paid = 'paid';
    case Packed = 'packed';
    case Shipped = 'shipped';
    case Delivered = 'delivered';
    case Cancelled = 'cancelled';
}
