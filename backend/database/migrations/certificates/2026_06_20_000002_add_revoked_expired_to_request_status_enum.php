<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Fase 1 (estados unificados): añade los estados finales REVOKED y EXPIRED a la columna
 * ENUM `certificate_requests.request_status`, que es la capa unificada del ciclo de vida
 * para ambos proveedores.
 *
 * - REVOKED: certificado revocado tras su emisión (revocación comercial o voluntaria).
 * - EXPIRED: certificado vencido naturalmente (pasada expiration_date).
 *
 * Se preservan todos los valores legacy existentes de la columna.
 */
return new class extends Migration
{
    private const ENUM_VALUES_WITH_NEW = "'DRAFT','SENT','CANCELLED','REJECTED','ON_HOLD','DEFINITIVE','CLOSED','OPEN','DELETED','PENDING','ACCEPTED','PROCESSING','PROCESSED','UNKNOWN','REVOKED','EXPIRED'";

    private const ENUM_VALUES_ORIGINAL = "'DRAFT','SENT','CANCELLED','REJECTED','ON_HOLD','DEFINITIVE','CLOSED','OPEN','DELETED','PENDING','ACCEPTED','PROCESSING','PROCESSED','UNKNOWN'";

    public function up(): void
    {
        DB::statement(
            "ALTER TABLE `certificate_requests` MODIFY `request_status` ENUM(" . self::ENUM_VALUES_WITH_NEW . ") NULL DEFAULT 'DRAFT'"
        );
    }

    public function down(): void
    {
        // Reasigna cualquier registro con los nuevos estados antes de revertir el enum,
        // para no perder filas por valores fuera del conjunto original.
        DB::table('certificate_requests')->where('request_status', 'REVOKED')->update(['request_status' => 'REJECTED']);
        DB::table('certificate_requests')->where('request_status', 'EXPIRED')->update(['request_status' => 'PROCESSED']);

        DB::statement(
            "ALTER TABLE `certificate_requests` MODIFY `request_status` ENUM(" . self::ENUM_VALUES_ORIGINAL . ") NULL DEFAULT 'DRAFT'"
        );
    }
};
