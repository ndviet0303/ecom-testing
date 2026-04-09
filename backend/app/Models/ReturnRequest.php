<?php

namespace App\Models;

use App\Domain\Ecommerce\ReturnRequest\ReturnStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ReturnRequest extends Model
{
    protected $fillable = [
        'user_id',
        'order_id',
        'order_item_id',
        'quantity',
        'reason',
        'status',
        'staff_note',
    ];

    protected function casts(): array
    {
        return [
            'quantity' => 'integer',
            'status' => ReturnStatus::class,
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function orderItem(): BelongsTo
    {
        return $this->belongsTo(OrderItem::class);
    }
}
