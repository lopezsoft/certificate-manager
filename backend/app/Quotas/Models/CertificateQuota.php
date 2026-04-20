<?php

namespace App\Quotas\Models;

use App\Models\Company;
use App\Models\User;
use App\Quotas\Enums\QuotaStatusEnum;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CertificateQuota extends Model
{
    protected $table = 'certificate_quotas';

    protected $fillable = [
        'company_id',
        'allocated_quantity',
        'used_quantity',
        'period_start',
        'period_end',
        'status',
        'billing_type',
        'assigned_by',
        'notes',
    ];

    protected $casts = [
        'allocated_quantity' => 'integer',
        'used_quantity'      => 'integer',
        'period_start'       => 'date',
        'period_end'         => 'date',
    ];

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class, 'company_id');
    }

    public function assignedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_by');
    }

    public function getStatusEnum(): QuotaStatusEnum
    {
        return QuotaStatusEnum::from($this->status);
    }

    public function getRemaining(): int
    {
        return max(0, $this->allocated_quantity - $this->used_quantity);
    }

    public function isUsable(): bool
    {
        return $this->getStatusEnum()->isUsable()
            && $this->getRemaining() > 0
            && $this->period_end->isFuture();
    }
}

