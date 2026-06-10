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
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Agregado raíz del bounded context "Viafirma Issuance".
 *
 * 1:1 con {@see CertificateRequest} (el agregado de negocio histórico).
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
 * @property InternalState $internal_state
 * @property string|null $remote_status
 * @property string $key_vault_ref
 * @property string $csr_fingerprint
 * @property string|null $csr_pem
 * @property string|null $p7b_storage_path
 * @property string|null $p12_storage_path
 * @property string|null $p12_password_ref
 * @property array|null $request_payload
 * @property array|null $last_status_response
 * @property int $poll_attempts
 * @property \Illuminate\Support\Carbon|null $next_poll_at
 * @property \Illuminate\Support\Carbon|null $last_polled_at
 * @property \Illuminate\Support\Carbon|null $submitted_at
 * @property \Illuminate\Support\Carbon|null $downloaded_at
 * @property \Illuminate\Support\Carbon|null $assembled_at
 * @property \Illuminate\Support\Carbon|null $expires_at
 * @property string|null $last_error_code
 * @property string|null $last_error_message
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
        'internal_state',
        'remote_status',
        'key_vault_ref',
        'csr_fingerprint',
        'csr_pem',
        'p7b_storage_path',
        'p12_storage_path',
        'p12_password_ref',
        'request_payload',
        'last_status_response',
        'poll_attempts',
        'next_poll_at',
        'last_polled_at',
        'submitted_at',
        'downloaded_at',
        'assembled_at',
        'expires_at',
        'last_error_code',
        'last_error_message',
        'revocation_request_code',
        'revoked_at',
    ];

    protected $casts = [
        'profile_type'         => CertificateProfile::class,
        'identity_type'        => IdentityType::class,
        'organization_type'    => OrganizationType::class,
        'internal_state'       => InternalState::class,
        'validity_days'        => 'integer',
        'poll_attempts'        => 'integer',
        'request_payload'      => 'array',
        'last_status_response' => 'array',
        'next_poll_at'         => 'datetime',
        'last_polled_at'       => 'datetime',
        'submitted_at'         => 'datetime',
        'downloaded_at'        => 'datetime',
        'assembled_at'         => 'datetime',
        'expires_at'           => 'datetime',
        'revoked_at'           => 'datetime',
    ];

    /**
     * Oculta artefactos sensibles por defecto — evita serializaciones
     * accidentales en respuestas API.
     */
    protected $hidden = [
        'csr_pem',
        'key_vault_ref',
        'p12_password_ref',
        'request_payload',
        'last_status_response',
    ];

    // ── Relaciones ─────────────────────────────────────────────────────────

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

    // ── Helpers de FSM ─────────────────────────────────────────────────────

    public function isTerminal(): bool
    {
        return $this->internal_state instanceof InternalState
            && $this->internal_state->isTerminal();
    }

    public function hasExpired(): bool
    {
        return $this->expires_at !== null && $this->expires_at->isPast();
    }

    public function isReadyToDownload(): bool
    {
        return $this->internal_state === InternalState::READY_TO_DOWNLOAD;
    }

    public function isFailed(): bool
    {
        return $this->internal_state instanceof InternalState
            && $this->internal_state->isFailureLike();
    }
}

