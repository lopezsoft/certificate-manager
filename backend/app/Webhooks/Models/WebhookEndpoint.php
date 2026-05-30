<?php

namespace App\Webhooks\Models;

use App\Models\Company;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class WebhookEndpoint extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'company_id',
        'name',
        'url',
        'secret',
        'events',
        'is_active',
        'description',
        'last_triggered_at',
        'failure_count',
    ];

    protected $casts = [
        'events'             => 'array',
        'is_active'          => 'boolean',
        'last_triggered_at'  => 'datetime',
    ];

    protected $hidden = ['secret'];

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function deliveries(): HasMany
    {
        return $this->hasMany(WebhookDelivery::class);
    }

    public function listensTo(string $eventType): bool
    {
        return in_array($eventType, $this->events, true)
            || in_array('*', $this->events, true);
    }
}
