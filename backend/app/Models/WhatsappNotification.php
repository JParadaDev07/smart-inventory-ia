<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class WhatsappNotification extends Model
{
    protected $fillable = [
        'business_id',
        'product_id',
        'type',
        'status',
        'recipient',
        'meta',
        'last_sent_at',
        'next_run_at',
    ];

    protected $casts = [
        'meta' => 'array',
        'last_sent_at' => 'datetime',
        'next_run_at' => 'datetime',
    ];
}

