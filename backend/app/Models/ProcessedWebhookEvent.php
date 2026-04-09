<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ProcessedWebhookEvent extends Model
{
    protected $fillable = [
        'provider',
        'event_id',
        'checksum',
    ];
}
