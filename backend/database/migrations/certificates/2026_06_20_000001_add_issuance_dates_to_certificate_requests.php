<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Fase 0 (ciclo de vida): persistir la emisión y el vencimiento en certificate_requests,
 * que es la fuente de verdad del ciclo de vida para ambos proveedores.
 *
 * - issued_at:     fecha de emisión real del certificado (validFrom del X.509).
 * - cert_valid_to: vencimiento real del certificado (validTo del X.509). Para Viafirma siempre
 *                  ~2 años; se guarda para auditoría. El vencimiento COMERCIAL vive en
 *                  expiration_date (issued_at + life años).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('certificate_requests', function (Blueprint $table) {
            $table->dateTime('issued_at')
                ->nullable()
                ->after('expiration_date')
                ->comment('Fecha de emisión real del certificado (validFrom del X.509).');

            $table->dateTime('cert_valid_to')
                ->nullable()
                ->after('issued_at')
                ->comment('Vencimiento real del certificado (validTo del X.509). El comercial vive en expiration_date.');
        });
    }

    public function down(): void
    {
        Schema::table('certificate_requests', function (Blueprint $table) {
            $table->dropColumn(['issued_at', 'cert_valid_to']);
        });
    }
};
