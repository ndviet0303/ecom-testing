<?php

namespace App\Domain\Ecommerce\ReturnRequest;

enum ReturnStatus: string
{
    case Pending = 'pending';
    case Approved = 'approved';
    case Rejected = 'rejected';
    case Received = 'received';
    case Refunded = 'refunded';
}
