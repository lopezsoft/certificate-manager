<?php

declare(strict_types=1);

namespace App\Modules\Viafirma\Domain\Enums;

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
 *                                              DOWNLOADED ─► ASSEMBLED ─► COMPLETED
 *
 *   Cualquier estado puede transicionar a FAILED, FAILED_RECOVERABLE o EXPIRED.
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
    case FAILED             = 'FAILED';
    case FAILED_RECOVERABLE = 'FAILED_RECOVERABLE';
    case EXPIRED            = 'EXPIRED';

    /** Estados terminales — el job de polling debe detenerse al alcanzar uno. */
    public function isTerminal(): bool
    {
        return in_array($this, [
            self::COMPLETED,
            self::FAILED,
            self::EXPIRED,
        ], true);
    }

    public function isFailureLike(): bool
    {
        return in_array($this, [self::FAILED, self::FAILED_RECOVERABLE, self::EXPIRED], true);
    }
}

