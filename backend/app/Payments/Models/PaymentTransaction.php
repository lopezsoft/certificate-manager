<?php

namespace App\Payments\Models;

use App\Payments\Enums\PaymentStatusEnum;
use App\Quotas\Models\CertificateOrder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PaymentTransaction extends Model
{
    protected $table = 'payment_transactions';

    protected $fillable = [
        'certificate_order_id',
        'wompi_transaction_id',
        'wompi_reference',
        'status',
        'amount_in_cents',
        'currency',
        'payment_method_type',
        'wompi_raw_response',
        'acceptance_token',
        'paid_at',
    ];

    protected $casts = [
        'amount_in_cents'    => 'integer',
        'wompi_raw_response' => 'array',
        'paid_at'            => 'datetime',
    ];

    public function order(): BelongsTo
    {
        return $this->belongsTo(CertificateOrder::class, 'certificate_order_id');
    }

    public function getStatusEnum(): PaymentStatusEnum
    {
        return PaymentStatusEnum::from($this->status);
    }

    public function isApproved(): bool
    {
        return $this->getStatusEnum()->isSuccessful();
    }
}

