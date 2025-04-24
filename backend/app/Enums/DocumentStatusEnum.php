<?php

namespace App\Enums;

use InvalidArgumentException;

/**
 * Estado del documento para las facturas.
 * @property $value
 */
enum DocumentStatusEnum: string {
    case DRAFT = 'DRAFT';  // Borrador
    case SENT = 'SENT';  // Enviada
    case OVERDUE = 'OVERDUE';  // Vencida
    case PAID = 'PAID';  // Pagada
    case PARTIALLY_PAID = 'PARTIALLY_PAID';  // Parcialmente Pagada
    case CANCELLED = 'CANCELLED';  // Cancelada
    case REJECTED = 'REJECTED';  // Rechazada
    case DISPUTED = 'DISPUTED';  // En Disputa
    case REFUNDED = 'REFUNDED';  // Reembolsada
    case ON_HOLD = 'ON_HOLD';  // En Espera
    case DEFINITIVE = 'DEFINITIVE';  // Definitiva
    case CLOSED = 'CLOSED';  // Cerrada
    case OPEN = 'OPEN';  // Abierta
    case DELETED = 'DELETED';  // Eliminada
    case PENDING = 'PENDING';  // Pendiente
    case ANNULLED = 'ANNULLED';  // Anulado
    case ACCEPTED = 'ACCEPTED';  // Aceptado
    case PROCESSING = 'PROCESSING';  // Procesando
    case PROCESSED = 'PROCESSED';  // Procesado
    case ACCOUNTED = 'ACCOUNTED';  // Contabilizado
    case UNKNOWN = 'UNKNOWN';  // Desconocido
    public static function getDraft(): string {
        return self::DRAFT->value;
    }

    public static function getSent(): string {
        return self::SENT->value;
    }

    public static function getOverdue(): string {
        return self::OVERDUE->value;
    }

    public static function getPaid(): string {
        return self::PAID->value;
    }

    public static function getPartiallyPaid(): string {
        return self::PARTIALLY_PAID->value;
    }

    public static function getCancelled(): string {
        return self::CANCELLED->value;
    }

    public static function getRejected(): string {
        return self::REJECTED->value;
    }

    public static function getDisputed(): string {
        return self::DISPUTED->value;
    }

    public static function getRefunded(): string {
        return self::REFUNDED->value;
    }

    public static function getOnHold(): string {
        return self::ON_HOLD->value;
    }
    public static function getDefinitive(): string {
        return self::DEFINITIVE->value;
    }

    public static function getClosed(): string {
        return self::CLOSED->value;
    }

    public static function getOpen(): string {
        return self::OPEN->value;
    }

    public static function getDeleted(): string {
        return self::DELETED->value;
    }

    public static function getPending(): string {
        return self::PENDING->value;
    }
    public static function getAnnulled(): string {
        return self::ANNULLED->value;
    }

    public static function getAccepted(): string {
        return self::ACCEPTED->value;
    }

    public static function getProcessing(): string {
        return self::PROCESSING->value;
    }

    public static function getProcessed(): string {
        return self::PROCESSED->value;
    }

    public static function getAccounted(): string {
        return self::ACCOUNTED->value;
    }

    public static function getUnknown(): string {
        return self::UNKNOWN->value;
    }

    /**
     * @throws \InvalidArgumentException
     */
    public static function getDescription(string $status): string {
        return match($status) {
            self::DRAFT->value          => 'Borrador',
            self::SENT->value           => 'Enviado',
            self::OVERDUE->value        => 'Vencido',
            self::PAID->value           => 'Pagado/a',
            self::PARTIALLY_PAID->value => 'Parcialmente Pagado',
            self::CANCELLED->value      => 'Cancelado',
            self::REJECTED->value       => 'Rechazado',
            self::DISPUTED->value       => 'En Disputa',
            self::REFUNDED->value       => 'Reembolsado',
            self::ON_HOLD->value        => 'En Espera',
            self::DEFINITIVE->value     => 'Definitivo',
            self::CLOSED->value         => 'Cerrado',
            self::OPEN->value           => 'Abierto',
            self::DELETED->value        => 'Eliminado',
            self::PENDING->value        => 'Pendiente',
            self::ANNULLED->value       => 'Anulado',
            self::ACCEPTED->value       => 'Aceptado',
            self::ACCOUNTED->value      => 'Contabilizado',
            self::PROCESSED->value      => 'Procesado',
            self::PROCESSING->value     => 'Procesando',
            default => 'Desconocido',
        };
    }
}
