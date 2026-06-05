<?php

declare(strict_types=1);

namespace App\Models;

use App\Quotas\Models\CertificateQuota;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Catálogo agnóstico de rangos de precios (3NF).
 *
 * Almacena exclusivamente definiciones de rango y precio.
 * La asignación a empresas se gestiona en CertificateQuota.
 *
 * Montos en valor real COP (DECIMAL), NO en centavos.
 */
class PricingTier extends Model
{
    protected $table = 'pricing_tiers';

    protected $fillable = [
        'code',
        'user_type_id',
        'name',
        'min_quantity',
        'max_quantity',
        'price_1yr',
        'price_2yr',
        'is_active',
        'sort_order',
    ];

    protected $casts = [
        'user_type_id' => 'integer',
        'min_quantity' => 'integer',
        'max_quantity' => 'integer',
        'price_1yr'    => 'decimal:2',
        'price_2yr'    => 'decimal:2',
        'is_active'    => 'boolean',
        'sort_order'   => 'integer',
    ];

    public function userType(): BelongsTo
    {
        return $this->belongsTo(UserType::class, 'user_type_id');
    }

    public function certificateQuotas(): HasMany
    {
        return $this->hasMany(CertificateQuota::class, 'pricing_tier_id');
    }

    /**
     * Obtiene el precio según la vigencia.
     */
    public function getPriceForVigencia(int $years): string
    {
        return match ($years) {
            1 => $this->price_1yr,
            2 => $this->price_2yr,
            default => throw new \InvalidArgumentException("Vigencia {$years} no soportada."),
        };
    }
}
