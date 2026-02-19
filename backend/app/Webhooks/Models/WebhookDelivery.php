<?php

namespace App\Webhooks\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WebhookDelivery extends Model
{
    protected $fillable = [
        'webhook_endpoint_id',
        'event_type',
        'payload',
        'signature',
        'http_status',
        'response_body',
        'status',
        'attempt',
        'delivered_at',
    ];

    protected $casts = [
        'payload'      => 'array',
        'delivered_at' => 'datetime',
    ];

    public function endpoint(): BelongsTo
    {
        return $this->belongsTo(WebhookEndpoint::class, 'webhook_endpoint_id');
    }

    public function isDelivered(): bool
    {
        return $this->status === 'delivered';
    }
}
