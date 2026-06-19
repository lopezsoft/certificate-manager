<?php

declare(strict_types=1);

namespace App\Modules\Viafirma\Infrastructure\Persistence\Models;

use App\Models\CertificateRequest;
use App\Models\Company;
use App\Models\User;
use App\Modules\Viafirma\Domain\Enums\CertificateProfile;
use App\Modules\Viafirma\Domain\Enums\IdentityType;
use App\Modules\Viafirma\Domain\Enums\InternalState;
use App\Modules\Viafirma\Domain\Enums\OrganizationType;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Agregado raíz del bounded context "Viafirma Issuance" — Identidad del trámite.
 *
 * 1:1 con {@see CertificateRequest} (el agregado de negocio histórico).
 * 1:1 con {@see ViafirmaCertificateRequestState} (ciclo de vida y estado).
 *
 * Esta tabla contiene únicamente los datos de identidad del trámite:
 * quién lo solicitó, para qué empresa, con qué perfil y parámetros.
 * Todo lo relacionado con el ciclo de vida (estado FSM, polling, criptografía)
 * se encuentra en {@see ViafirmaCertificateRequestState}.
 *
 * @property int $id
 * @property int $certificate_request_id
 * @property int $company_id
 * @property int|null $requested_by_user_id
 * @property string|null $cod_request
 * @property string|null $public_id
 * @property string|null $cod_profile
 * @property string $ra_code
 * @property CertificateProfile $profile_type
 * @property IdentityType $identity_type
 * @property string $country_code
 * @property OrganizationType|null $organization_type
 * @property int $validity_days
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property \Illuminate\Support\Carbon|null $deleted_at
 *
 * @property-read ViafirmaCertificateRequestState|null $state
 */
class ViafirmaCertificateRequest extends Model
{
    use SoftDeletes;
    use HasFactory;

    protected $table = 'viafirma_certificate_requests';

    protected $fillable = [
        'certificate_request_id',
        'company_id',
        'requested_by_user_id',
        'cod_request',
        'public_id',
        'cod_profile',
        'ra_code',
        'profile_type',
        'identity_type',
        'country_code',
        'organization_type',
        'validity_days',
    ];

    protected $casts = [
        'profile_type'      => CertificateProfile::class,
        'identity_type'     => IdentityType::class,
        'organization_type' => OrganizationType::class,
        'validity_days'     => 'integer',
    ];

    // ── Relaciones ─────────────────────────────────────────────────────────

    /**
     * Estado y ciclo de vida del trámite (1:1).
     * Acceso: $entity->state->internal_state
     */
    public function state(): HasOne
    {
        return $this->hasOne(
            ViafirmaCertificateRequestState::class,
            'viafirma_certificate_request_id'
        );
    }

    public function certificateRequest(): BelongsTo
    {
        return $this->belongsTo(CertificateRequest::class, 'certificate_request_id');
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class, 'company_id');
    }

    public function requestedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by_user_id');
    }

    public function statusHistory(): HasMany
    {
        return $this->hasMany(ViafirmaStatusHistory::class, 'viafirma_certificate_request_id');
    }

    // ── Helpers de FSM (delegados al estado) ───────────────────────────────

    /**
     * Verifica si el trámite está en un estado terminal.
     * Requiere que la relación `state` esté cargada.
     */
    public function isTerminal(): bool
    {
        return $this->state?->isTerminal() ?? false;
    }

    /**
     * Verifica si el certificado ha expirado.
     * Requiere que la relación `state` esté cargada.
     */
    public function hasExpired(): bool
    {
        return $this->state?->hasExpired() ?? false;
    }

    /**
     * Verifica si el trámite está listo para descargar.
     * Requiere que la relación `state` esté cargada.
     */
    public function isReadyToDownload(): bool
    {
        return $this->state?->isReadyToDownload() ?? false;
    }

    /**
     * Verifica si el trámite está en un estado de fallo.
     * Requiere que la relación `state` esté cargada.
     */
    public function isFailed(): bool
    {
        return $this->state?->isFailed() ?? false;
    }

    // ── Accesores de conveniencia (proxy al estado) ─────────────────────────

    /**
     * Acceso directo al internal_state sin necesidad de $entity->state->internal_state.
     * Útil para compatibilidad con código existente.
     */
    public function getInternalStateAttribute(): ?InternalState
    {
        return $this->state?->internal_state;
    }
}
