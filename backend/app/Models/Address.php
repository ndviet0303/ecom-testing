<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Address extends Model
{
    protected $fillable = [
        'user_id',
        'country',
        'label',
        'recipient_name',
        'phone',
        'line1',
        'line2',
        'ward',
        'district',
        'province',
        'postal_code',
        'is_default',
    ];

    protected function casts(): array
    {
        return [
            'is_default' => 'boolean',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function toSnapshotArray(): array
    {
        return $this->only([
            'country',
            'recipient_name', 'phone', 'line1', 'line2', 'ward', 'district', 'province', 'postal_code',
        ]);
    }
}
