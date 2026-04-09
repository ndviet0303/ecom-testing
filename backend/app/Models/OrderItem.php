<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class OrderItem extends Model
{
    protected $appends = ['is_under_warranty'];

    protected $fillable = [
        'order_id',
        'product_id',
        'sku',
        'name',
        'quantity',
        'unit_price_cents',
        'serial_number',
        'warranty_expires_at',
    ];

    protected function casts(): array
    {
        return [
            'quantity' => 'integer',
            'unit_price_cents' => 'integer',
            'warranty_expires_at' => 'datetime',
        ];
    }

    public function getIsUnderWarrantyAttribute(): bool
    {
        if ($this->warranty_expires_at === null) {
            return false;
        }

        return $this->warranty_expires_at->isFuture();
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function returnRequests(): HasMany
    {
        return $this->hasMany(ReturnRequest::class);
    }
}
