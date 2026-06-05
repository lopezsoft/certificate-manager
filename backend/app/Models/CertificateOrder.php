<?php

namespace App\Models;

use App\Models\Company;
use App\Models\User;
use App\Enums\OrderStatusEnum;
use App\Payments\Models\PaymentTransaction;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class CertificateOrder extends Model
{
    protected $table = 'certificate_orders';

    protected $fillable = [
        'uuid',
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
        'payment_provider',
        'provider_reference',
        'payment_method',
    ];

    protected $hidden = ['id'];

    protected $casts = [
        'quantity'     => 'integer',
        'vigencia'     => 'integer',
        'unit_price'   => 'decimal:2',
        'subtotal'     => 'decimal:2',
        'tax_amount'   => 'decimal:2',
        'total_amount' => 'decimal:2',
    ];

    protected static function boot(): void
    {
        parent::boot();

        static::creating(function (self $model) {
            if (empty($model->uuid)) {
                $model->uuid = (string) Str::uuid();
            }
        });
    }

    /**
     * El frontend identifica órdenes por UUID, no por ID secuencial.
     */
    public function getRouteKeyName(): string
    {
        return 'uuid';
    }

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
}
