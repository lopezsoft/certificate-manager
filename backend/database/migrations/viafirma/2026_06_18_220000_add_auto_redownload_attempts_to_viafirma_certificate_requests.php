<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Agrega el contador de intentos de re-descarga automática a viafirma_certificate_requests.
 *
 * Usado por AutoRedownloadPendingViafirmaJob para limitar los reintentos automáticos
 * a un máximo de 5 antes de escalar a intervención manual del ADMIN.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('viafirma_certificate_requests', function (Blueprint $table) {
            $table->unsignedTinyInteger('auto_redownload_attempts')
                ->nullable()
                ->default(0)
                ->after('revoked_at')
                ->comment('Contador de intentos automáticos de re-descarga. Máximo 5 antes de requerir intervención manual.');
        });
    }

    public function down(): void
    {
        Schema::table('viafirma_certificate_requests', function (Blueprint $table) {
            $table->dropColumn('auto_redownload_attempts');
        });
    }
};
