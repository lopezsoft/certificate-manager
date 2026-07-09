<?php

namespace App\Enums;

/**
 * Estados posibles de una solicitud de certificado digital.
 *
 * Centraliza los strings de estado que antes estaban hardcodeados en 15+ archivos,
 * eliminando el riesgo de errores tipográficos y facilitando refactorizaciones futuras.
 */
enum CertificateRequestStatusEnum: string
{
    case DRAFT      = 'DRAFT';
    case SENT       = 'SENT';
    case PENDING    = 'PENDING';
    case ACCEPTED   = 'ACCEPTED';
    case PROCESSING = 'PROCESSING';
    case PROCESSED  = 'PROCESSED';
    case REJECTED   = 'REJECTED';
    case REVOKED    = 'REVOKED';
    case EXPIRED    = 'EXPIRED';
    case CANCELLED  = 'CANCELLED';

    /**
     * Descripción legible del estado.
     */
    public function description(): string
    {
        return match ($this) {
            self::DRAFT      => 'Borrador',
            self::SENT       => 'Enviada',
            self::PENDING    => 'Pendiente',
            self::ACCEPTED   => 'Aceptada',
            self::PROCESSING => 'En Proceso',
            self::PROCESSED  => 'Procesada',
            self::REJECTED   => 'Rechazada',
            self::REVOKED    => 'Revocada',
            self::EXPIRED    => 'Vencida',
            self::CANCELLED  => 'Cancelada',
        };
    }

    /**
     * Estados que representan solicitudes "activas" (no finalizadas).
     *
     * @return string[]
     */
    public static function activeStatuses(): array
    {
        return [
            self::DRAFT->value,
            self::SENT->value,
            self::PENDING->value,
            self::ACCEPTED->value,
            self::PROCESSING->value,
        ];
    }

    /**
     * Estados en los que se considera que el certificado fue emitido.
     *
     * @return string[]
     */
    public static function issuedStatuses(): array
    {
        return [
            self::PROCESSED->value,
            self::PROCESSING->value
        ];
    }

    /**
     * Estados para los filtros de reportes y paneles de administración.
     *
     * @return string[]
     */

    public static function reportStatuses(): array
    {
        return [
            self::PROCESSING->value,
            self::PROCESSED->value,
            self::EXPIRED->value,
        ];
    }

    /**
     * Estados visibles en el panel de administración por defecto.
     *
     * @return string[]
     */
    public static function adminDefaultStatuses(): array
    {
        return [
            self::SENT->value,
            self::PENDING->value,
            self::PROCESSING->value,
            self::ACCEPTED->value,
        ];
    }

    /**
     * Todos los valores como array de strings.
     *
     * @return string[]
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }

    /**
     * Mapa de transiciones de estado permitidas.
     *
     * Cada estado define a qué estados puede transicionar.
     * Si un estado no está aquí, no se permite la transición.
     *
     * @return array<string, string[]>
     */
    public static function allowedTransitions(): array
    {
        return [
            self::DRAFT->value      => [self::SENT->value, self::REJECTED->value],
            self::SENT->value       => [self::PENDING->value, self::ACCEPTED->value, self::REJECTED->value],
            self::PENDING->value    => [self::ACCEPTED->value, self::REJECTED->value],
            self::ACCEPTED->value   => [self::PROCESSING->value, self::REJECTED->value],
            self::PROCESSING->value => [self::PROCESSED->value, self::REJECTED->value],
            // PROCESSED ya no es absolutamente terminal: un certificado emitido puede
            // revocarse (revocación comercial/voluntaria) o vencer naturalmente.
            self::PROCESSED->value  => [self::REVOKED->value, self::EXPIRED->value],
            self::REJECTED->value   => [self::DRAFT->value, self::SENT->value, self::CANCELLED->value], // Permite reabrir
            self::REVOKED->value    => [], // Estado final
            self::EXPIRED->value    => [], // Estado final
            self::CANCELLED->value  => [], // Estado final
        ];
    }

    /**
     * Verifica si una transición de estado es válida.
     */
    public static function canTransitionTo(string $from, string $to): bool
    {
        $allowed = self::allowedTransitions()[$from] ?? [];

        return in_array($to, $allowed, true);
    }
}
