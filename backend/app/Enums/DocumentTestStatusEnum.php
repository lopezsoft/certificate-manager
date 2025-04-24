<?php

namespace App\Enums;
/**
 * Estado del documento para test de los documentos electrónicos.
 * @property $value
 */
enum DocumentTestStatusEnum: string {
    case CREATED = 'CREATED';  // Creado
    case RUNNING = 'RUNNING';  // En Ejecución
    case VALIDATING = 'VALIDATING';  // Validando
    case FINISHED = 'FINISHED';  // Finalizado
    case ERROR = 'ERROR';  // Error
    case SENDING = 'SENDING';  // Enviando
    case UNKNOWN = 'UNKNOWN';  // Desconocido
    public static function getCreated(): string {
        return self::CREATED->value;
    }
    public static function getRunning(): string {
        return self::RUNNING->value;
    }
    public static function getValidating(): string {
        return self::VALIDATING->value;
    }

    public static function getFinished(): string {
        return self::FINISHED->value;
    }

    public static function getError(): string {
        return self::ERROR->value;
    }

    public static function getSending()
    {
        return self::SENDING->value;
    }
    public static function getUnknown(): string {
        return self::UNKNOWN->value;
    }

    /**
     * @throws \InvalidArgumentException
     */
    public static function getDescription(string $status): string {
        return match($status) {
            self::CREATED->value => 'Creado',
            self::RUNNING->value => 'En Ejecución',
            self::VALIDATING->value => 'Validando',
            self::FINISHED->value => 'Finalizado',
            self::SENDING->value  => 'Enviado a la DIAN',
            self::ERROR->value => 'Error',
            default => 'Desconocido',
        };
    }
}
