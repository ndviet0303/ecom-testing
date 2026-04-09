<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Order extends Model
{
    use HasFactory;
    protected $fillable = [
        'user_id',
        'order_number',
        'shipping_address_id',
        'shipping_address_snapshot',
        'weight_grams',
        'shipping_zone_id',
        'status',
        'subtotal_cents',
        'discount_cents',
        'shipping_cents',
        'tax_cents',
        'total_cents',
        'coupon_code',
        'tracking_number',
        'tracking_carrier',
        'customer_note',
        'internal_note',
        'cancelled_at',
        'cancel_reason',
    ];

    protected function casts(): array
    {
        return [
            'shipping_address_snapshot' => 'array',
            'weight_grams' => 'integer',
            'subtotal_cents' => 'integer',
            'discount_cents' => 'integer',
            'shipping_cents' => 'integer',
            'tax_cents' => 'integer',
            'total_cents' => 'integer',
            'cancelled_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function shippingAddress(): BelongsTo
    {
        return $this->belongsTo(Address::class, 'shipping_address_id');
    }

    public function shippingZone(): BelongsTo
    {
        return $this->belongsTo(ShippingZone::class, 'shipping_zone_id');
    }

    public function orderItems(): HasMany
    {
        return $this->hasMany(OrderItem::class);
    }

    public function returnRequests(): HasMany
    {
        return $this->hasMany(ReturnRequest::class);
    }

    public function statusEvents(): HasMany
    {
        return $this->hasMany(OrderStatusEvent::class)->orderBy('id');
    }
}
