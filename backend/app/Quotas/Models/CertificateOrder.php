<?php

namespace App\Quotas\Models;

use App\Models\Company;
use App\Models\User;
use App\Quotas\Enums\OrderStatusEnum;
use App\Payments\Models\PaymentTransaction;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CertificateOrder extends Model
{
    protected $table = 'certificate_orders';

    protected $fillable = [
        'company_id',
        'user_id',
        'quantity',
        'vigencia',
        'unit_price',
        'subtotal',
        'tax_amount',
        'total_amount',
        'currency',
        'status',
        'payment_method',
        'wompi_reference',
    ];

    protected $casts = [
        'quantity'     => 'integer',
        'vigencia'     => 'integer',
        'unit_price'   => 'integer',
        'subtotal'     => 'integer',
        'tax_amount'   => 'integer',
        'total_amount' => 'integer',
    ];

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class, 'company_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function items(): HasMany
    {
        return $this->hasMany(CertificateOrderItem::class, 'certificate_order_id');
    }

    public function transactions(): HasMany
    {
        return $this->hasMany(PaymentTransaction::class, 'certificate_order_id');
    }

    public function latestTransaction(): \Illuminate\Database\Eloquent\Relations\HasOne
    {
        return $this->hasOne(PaymentTransaction::class, 'certificate_order_id')->latest();
    }

    public function getStatusEnum(): OrderStatusEnum
    {
        return OrderStatusEnum::from($this->status);
    }

    /** Monto total en centavos para WOMPI */
    public function getTotalInCents(): int
    {
        return $this->total_amount * 100;
    }
}

