<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Coupon extends Model
{
    /** @use HasFactory<\Database\Factories\CouponFactory> */
    use HasFactory;

    protected $fillable = [
        'code',
        'min_subtotal_cents',
        'discount_cents',
        'is_active',
        'expires_at',
        'max_uses',
        'max_uses_per_user',
    ];

    protected function casts(): array
    {
        return [
            'min_subtotal_cents' => 'integer',
            'discount_cents' => 'integer',
            'is_active' => 'boolean',
            'expires_at' => 'datetime',
            'max_uses' => 'integer',
            'max_uses_per_user' => 'integer',
        ];
    }

    public function isUsableAt(\DateTimeInterface $at): bool
    {
        if (! $this->is_active) {
            return false;
        }

        if ($this->expires_at && $this->expires_at->isBefore($at)) {
            return false;
        }

        return true;
    }

    public function redemptions(): HasMany
    {
        return $this->hasMany(CouponRedemption::class);
    }
}
