<?php

declare(strict_types=1);

namespace App\Modules\Viafirma\Infrastructure\Persistence\Models;

use App\Modules\Viafirma\Domain\Enums\InternalState;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Estado y ciclo de vida de un trámite Viafirma.
 *
 * Tabla: viafirma_certificate_request_states
 * Relación: 1:1 con {@see ViafirmaCertificateRequest}
 *
 * Contiene todo lo relacionado con el ciclo de vida del trámite:
 * - Estado FSM (internal_state, remote_status)
 * - Criptografía (key_vault_ref, csr_fingerprint, csr_pem, p7b/p12 paths)
 * - Polling (poll_attempts, next_poll_at, last_polled_at)
 * - Payloads (request_payload, last_status_response)
 * - Timestamps del ciclo de vida (submitted_at, downloaded_at, assembled_at, expires_at)
 * - Errores (last_error_code, last_error_message)
 * - Revocación (revocation_request_code, revoked_at)
 * - Re-descarga automática (auto_redownload_attempts)
 *
 * @property int $id
 * @property int $viafirma_certificate_request_id
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
 * @property string|null $revocation_request_code
 * @property \Illuminate\Support\Carbon|null $revoked_at
 * @property int|null $auto_redownload_attempts
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 */
class ViafirmaCertificateRequestState extends Model
{
    protected $table = 'viafirma_certificate_request_states';

    protected $fillable = [
        'viafirma_certificate_request_id',
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
        'auto_redownload_attempts',
    ];

    protected $casts = [
        'internal_state'       => InternalState::class,
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

    public function viafirmaCertificateRequest(): BelongsTo
    {
        return $this->belongsTo(
            ViafirmaCertificateRequest::class,
            'viafirma_certificate_request_id'
        );
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

    // ── Scopes ─────────────────────────────────────────────────────────────

    /**
     * Candidatos para re-descarga automática por AutoRedownloadPendingViafirmaJob.
     *
     * Criterios:
     * - Estado interno FAILED_RECOVERABLE (el job de ensamblado falló pero es recuperable)
     * - La llave privada NO fue purgada (key_vault_ref != 'PURGED' y no es null)
     * - Llevan al menos 2 minutos en ese estado (evitar colisión con reintentos activos)
     * - No han superado el máximo de intentos de re-descarga automática (max: 5)
     *
     * @param Builder $query
     * @return Builder
     */
    public function scopePendingAutoRedownload(Builder $query): Builder
    {
        return $query
            ->where('internal_state', InternalState::FAILED_RECOVERABLE->value)
            ->where('key_vault_ref', '!=', 'PURGED')
            ->whereNotNull('key_vault_ref')
            ->where('updated_at', '<', now()->subMinutes(2))
            ->where(function (Builder $q) {
                $q->whereNull('auto_redownload_attempts')
                  ->orWhere('auto_redownload_attempts', '<', 5);
            });
    }

    /**
     * Solicitudes en estado de polling activo (SUBMITTED o POLLING).
     */
    public function scopeActivePolling(Builder $query): Builder
    {
        return $query->whereIn('internal_state', [
            InternalState::SUBMITTED->value,
            InternalState::POLLING->value,
        ]);
    }

    /**
     * Solicitudes huérfanas: en polling pero sin next_poll_at programado
     * o con next_poll_at vencido hace más de N minutos.
     */
    public function scopeOrphanedPolling(Builder $query, int $staleMinutes = 20): Builder
    {
        $threshold = now()->subMinutes($staleMinutes);

        return $query
            ->activePolling()
            ->whereNotNull('viafirma_certificate_request_id')
            ->where(function (Builder $q) use ($threshold) {
                $q->whereNull('next_poll_at')
                  ->orWhere('next_poll_at', '<', $threshold);
            });
    }
}
