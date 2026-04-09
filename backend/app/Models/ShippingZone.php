<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ShippingZone extends Model
{
    protected $fillable = [
        'code',
        'name',
        'rate_per_kg_cents',
        'free_shipping_from_subtotal_cents',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'rate_per_kg_cents' => 'integer',
            'free_shipping_from_subtotal_cents' => 'integer',
            'is_active' => 'boolean',
        ];
    }
}
