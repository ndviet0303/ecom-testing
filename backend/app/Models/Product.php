<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

class Product extends Model
{
    use SoftDeletes;

    /** @use HasFactory<\Database\Factories\ProductFactory> */
    use HasFactory;

    protected $fillable = [
        'sku',
        'name',
        'description',
        'category',
        'brand',
        'base_price_cents',
        'sale_price_cents',
        'specs',
    ];

    protected function casts(): array
    {
        return [
            'specs' => 'array',
            'base_price_cents' => 'integer',
            'sale_price_cents' => 'integer',
        ];
    }

    public function inventory(): HasOne
    {
        return $this->hasOne(Inventory::class);
    }

    public function reviews(): HasMany
    {
        return $this->hasMany(Review::class);
    }

    public function effectivePriceCents(): int
    {
        return $this->sale_price_cents ?? $this->base_price_cents;
    }
}
