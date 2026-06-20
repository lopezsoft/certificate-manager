<?php

declare(strict_types=1);

namespace App\Modules\Viafirma\Application\Services;

use App\Enums\CertificateRequestStatusEnum;
use App\Models\ChangeHistory;
use App\Modules\Viafirma\Domain\Enums\RecoveryStrategy;
use App\Modules\Viafirma\Infrastructure\Persistence\Models\ViafirmaCertificateRequest;

/**
 * Decide y ejecuta la estrategia de recuperación para certificados en estado FAILED
 * (sincronizados a REJECTED en `certificate_requests`).
 *
 * Decisión D3 (clasificación por tipo de error en `last_error_code`):
 *  - Errores de DATOS/identidad → REOPEN (reabrir y editar la misma solicitud).
 *  - Errores técnicos / SLA / irrecuperables → RECREATE (crear una nueva solicitud).
 *
 * La clasificación es la responsabilidad central de este servicio; la orquestación del
 * RECREATE (alta de una nueva solicitud) la dispara el flujo de emisión existente.
 */
final class FailedCertificateRecoveryService
{
    /**
     * Códigos de error que representan problemas de DATOS/identidad corregibles por el
     * usuario reabriendo la misma solicitud. Provienen de RemoteStatus (stop-recoverable).
     *
     * @var string[]
     */
    private const DATA_ERROR_CODES = [
        'rues_error',             // RemoteStatus::RUES_ERROR — datos RUES incorrectos
        'accreditation_rejected', // RemoteStatus::ACCREDITATION_REJECTED — acreditación/identidad rechazada
    ];

    /**
     * Determina la estrategia de recuperación según el código de error registrado.
     *
     * Por defecto (código desconocido / técnico) se opta por RECREATE, que es la vía segura:
     * no arrastra datos técnicos viejos no relacionados con la información de la solicitud.
     */
    public function strategyFor(?string $lastErrorCode): RecoveryStrategy
    {
        if ($lastErrorCode !== null && in_array($lastErrorCode, self::DATA_ERROR_CODES, true)) {
            return RecoveryStrategy::REOPEN;
        }

        return RecoveryStrategy::RECREATE;
    }

    /**
     * Reabre la solicitud para edición (REJECTED → DRAFT) cuando la estrategia es REOPEN.
     *
     * @throws \DomainException Si el estado actual no permite reabrir.
     */
    public function reopen(ViafirmaCertificateRequest $entity, ?int $userId = null): void
    {
        $cr = $entity->certificateRequest;
        if ($cr === null) {
            throw new \DomainException("La solicitud Viafirma {$entity->id} no tiene certificate_request asociado.");
        }

        $from = (string) $cr->request_status;
        $to   = CertificateRequestStatusEnum::DRAFT->value;

        if (!CertificateRequestStatusEnum::canTransitionTo($from, $to)) {
            throw new \DomainException("No se permite reabrir desde el estado '{$from}'.");
        }

        $cr->update(['request_status' => $to]);

        ChangeHistory::create([
            'certificate_request_id' => $cr->id,
            'user_id'                => $userId,
            'user_of_change'         => $userId ? 'Admin (Recuperación Viafirma)' : 'SYSTEM (Recuperación Viafirma)',
            'status'                 => $to,
            'comments'               => 'Solicitud reabierta para corrección de datos tras fallo recuperable ' .
                                        "(código: {$entity->state?->last_error_code}).",
        ]);
    }
}
