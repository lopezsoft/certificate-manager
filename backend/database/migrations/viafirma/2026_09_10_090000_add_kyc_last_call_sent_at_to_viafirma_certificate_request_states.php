<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Marca cuándo se envió el aviso de "último llamado" (24h antes del
 * vencimiento del plazo de verificación KYC) a la Casa de Software.
 *
 * Usada por ExpireStalledKycAccreditationsJob (cron horario) para no
 * reenviar el aviso en cada corrida dentro de la misma ventana de 24h.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('viafirma_certificate_request_states', function (Blueprint $table) {
            $table->timestamp('kyc_last_call_sent_at')
                ->nullable()
                ->after('kyc_flow_completed_user_agent')
                ->comment('Cuándo se envió el aviso de último llamado (24h antes de expires_at). NULL = aún no enviado.');
        });
    }

    public function down(): void
    {
        Schema::table('viafirma_certificate_request_states', function (Blueprint $table) {
            $table->dropColumn('kyc_last_call_sent_at');
        });
    }
};
