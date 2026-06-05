<?php

declare(strict_types=1);

namespace App\Payments\Models;

use App\Payments\Enums\PaymentStatusEnum;
use App\Models\CertificateOrder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Modelo de transacciones de pago — Agnóstico de pasarela.
 *
 * Todos los montos se almacenan en valor real (COP con decimales).
 * La conversión a centavos u otro formato específico del proveedor
 * se realiza exclusivamente en la capa Adapter (WompiPaymentService, etc.).
 */
class PaymentTransaction extends Model
{
    protected $table = 'payment_transactions';

    protected $fillable = [
        'certificate_order_id',
        'payment_provider',
        'provider_transaction_id',
        'provider_reference',
        'status',
        'amount',
        'currency',
        'payment_method_type',
        'provider_raw_response',
        'signature_valid',
        'acceptance_token',
        'paid_at',
    ];

    protected $casts = [
        'amount'                => 'decimal:2',
        'provider_raw_response' => 'array',
        'signature_valid'       => 'boolean',
        'paid_at'               => 'datetime',
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
