<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Tabla pivote 3NF: asignación de rangos de precio a empresas.
 *
 * Une Company + PricingTier + cuota + billing_type.
 */
class CompanyQuotaAssignment extends Model
{
    protected $table = 'company_quota_assignments';

    protected $fillable = [
        'company_id',
        'pricing_tier_id',
        'billing_type',
        'allocated_quantity',
        'used_quantity',
        'period_start',
        'period_end',
        'is_active',
        'notes',
        'assigned_by',
    ];

    protected $casts = [
        'allocated_quantity' => 'integer',
        'used_quantity'      => 'integer',
        'period_start'       => 'date',
        'period_end'         => 'date',
        'is_active'          => 'boolean',
    ];

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class, 'company_id');
    }

    public function pricingTier(): BelongsTo
    {
        return $this->belongsTo(PricingTier::class, 'pricing_tier_id');
    }

    public function assignedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_by');
    }

    public function getRemaining(): int
    {
        return max(0, $this->allocated_quantity - $this->used_quantity);
    }

    public function isUsable(): bool
    {
        return $this->is_active
            && $this->getRemaining() > 0
            && ($this->period_end === null || $this->period_end->isFuture());
    }
}
