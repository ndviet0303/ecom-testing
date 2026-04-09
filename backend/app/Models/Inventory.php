<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Inventory extends Model
{
    protected $fillable = [
        'product_id',
        'on_hand',
        'reserved',
        'low_stock_threshold',
    ];

    protected function casts(): array
    {
        return [
            'on_hand' => 'integer',
            'reserved' => 'integer',
            'low_stock_threshold' => 'integer',
        ];
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function available(): int
    {
        return max(0, $this->on_hand - $this->reserved);
    }
}
