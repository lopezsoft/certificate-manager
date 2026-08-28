<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Agrega el registro de finalización del flujo KYC (MetaMap) del lado del
 * navegador del cliente — cuando MetaMap redirige de vuelta a nuestro
 * callback público tras completar la verificación de identidad.
 *
 * IMPORTANTE: esto es solo una señal de UX/analytics ("el cliente terminó el
 * flujo en su navegador"). NO significa que Viafirma aprobó la identidad —
 * eso lo sigue confirmando exclusivamente el polling real vía
 * GET /request/{codRequest}/status. No debe usarse para transicionar la FSM.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('viafirma_certificate_request_states', function (Blueprint $table) {
            $table->timestamp('kyc_flow_completed_at')
                ->nullable()
                ->after('kyc_accreditation_link')
                ->comment('Cuándo el navegador del cliente llegó al callback tras completar MetaMap. Señal de UX, no de aprobación real.');

            $table->string('kyc_flow_completed_ip', 45)
                ->nullable()
                ->after('kyc_flow_completed_at')
                ->comment('IP del navegador en el callback (IPv4/IPv6).');

            $table->string('kyc_flow_completed_user_agent', 500)
                ->nullable()
                ->after('kyc_flow_completed_ip');
        });
    }

    public function down(): void
    {
        Schema::table('viafirma_certificate_request_states', function (Blueprint $table) {
            $table->dropColumn(['kyc_flow_completed_at', 'kyc_flow_completed_ip', 'kyc_flow_completed_user_agent']);
        });
    }
};
