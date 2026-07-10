<?php

declare(strict_types=1);

namespace App\Modules\Viafirma\Domain\Enums;

use App\Enums\CertificateRequestStatusEnum;

/**
 * Estados internos del agregado `viafirma_certificate_requests` — FSM propia
 * de Certificate Manager (NO los estados remotos de Viafirma, que viajan en
 * la columna `remote_status`).
 *
 * Transiciones válidas (validadas por StateMachine, Sprint 3):
 *
 *   DRAFT ─► CSR_GENERATED ─► SUBMITTED ─► POLLING ─► READY_TO_DOWNLOAD
 *                                                    │
 *                                                    ▼
 *                                              DOWNLOADED ─► ASSEMBLED ─► COMPLETED ─► REVOKED
 *
 *   Cualquier estado puede transicionar a FAILED, FAILED_RECOVERABLE o EXPIRED.
 *   COMPLETED puede transicionar a REVOKED (revocación voluntaria del certificado emitido).
 */
enum InternalState: string
{
    case DRAFT              = 'DRAFT';
    case CSR_GENERATED      = 'CSR_GENERATED';
    case SUBMITTED          = 'SUBMITTED';
    case POLLING            = 'POLLING';
    case READY_TO_DOWNLOAD  = 'READY_TO_DOWNLOAD';
    case DOWNLOADED         = 'DOWNLOADED';
    case ASSEMBLED          = 'ASSEMBLED';
    case COMPLETED          = 'COMPLETED';
    case REVOKED            = 'REVOKED';
    case FAILED             = 'FAILED';
    case FAILED_RECOVERABLE = 'FAILED_RECOVERABLE';
    case EXPIRED            = 'EXPIRED';

    /** Estados terminales — el job de polling debe detenerse al alcanzar uno. */
    public function isTerminal(): bool
    {
        return in_array($this, [
            self::COMPLETED,
            self::REVOKED,
            self::FAILED,
            self::EXPIRED,
        ], true);
    }

    public function isFailureLike(): bool
    {
        return in_array($this, [self::FAILED, self::FAILED_RECOVERABLE, self::EXPIRED], true);
    }

    public function isRecoverable(): bool
    {
        return $this === self::FAILED_RECOVERABLE;
    }

    /**
     * Mapeo único y centralizado del estado técnico de Viafirma al estado unificado de
     * `certificate_requests` (fuente de verdad del ciclo de vida, agnóstica de proveedor).
     *
     * Evita literales dispersos en jobs/use-cases. Ver roadmap §3.
     */
    public function toRequestStatus(): CertificateRequestStatusEnum
    {
        return match ($this) {
            self::DRAFT              => CertificateRequestStatusEnum::DRAFT,
            self::CSR_GENERATED      => CertificateRequestStatusEnum::SENT,
            self::SUBMITTED,
            self::POLLING,
            self::READY_TO_DOWNLOAD,
            self::DOWNLOADED,
            self::ASSEMBLED,
            self::FAILED_RECOVERABLE => CertificateRequestStatusEnum::PROCESSING,
            self::COMPLETED          => CertificateRequestStatusEnum::PROCESSED,
            self::REVOKED            => CertificateRequestStatusEnum::REVOKED,
            self::FAILED             => CertificateRequestStatusEnum::REJECTED,
            self::EXPIRED            => CertificateRequestStatusEnum::EXPIRED,
        };
    }
}

