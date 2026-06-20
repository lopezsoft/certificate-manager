<?php

declare(strict_types=1);

namespace App\Modules\Viafirma\Domain\Enums;

/**
 * Estrategia de recuperación para un certificado en estado FAILED (→ REJECTED en la capa unificada).
 *
 * Ver roadmap §3 "Manejo de FAILED → REJECTED (recuperación)" y decisión D3:
 *  - REOPEN:   el fallo es por DATOS/identidad corregibles → reabrir la misma solicitud
 *              (REJECTED → DRAFT), editar y reintentar la emisión.
 *  - RECREATE: el fallo es técnico/SLA/irrecuperable → descartar y crear una nueva solicitud.
 */
enum RecoveryStrategy: string
{
    case REOPEN   = 'REOPEN';
    case RECREATE = 'RECREATE';

    public function description(): string
    {
        return match ($this) {
            self::REOPEN   => 'Reabrir y editar la misma solicitud (datos corregibles).',
            self::RECREATE => 'Descartar y crear una nueva solicitud (fallo técnico o irrecuperable).',
        };
    }
}
