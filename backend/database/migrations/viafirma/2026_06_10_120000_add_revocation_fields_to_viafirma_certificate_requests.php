<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Agrega los campos de revocación a viafirma_certificate_requests.
 *
 * - revocation_request_code: código devuelto por Viafirma tras revocar el certificado.
 * - revoked_at: timestamp en que se ejecutó la revocación.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('viafirma_certificate_requests', function (Blueprint $table) {
            $table->string('revocation_request_code')->nullable()->after('last_error_message')
                ->comment('Código devuelto por Viafirma al revocar el certificado.');
            $table->timestamp('revoked_at')->nullable()->after('revocation_request_code')
                ->comment('Timestamp en que se ejecutó la revocación exitosamente.');
        });
    }

    public function down(): void
    {
        Schema::table('viafirma_certificate_requests', function (Blueprint $table) {
            $table->dropColumn(['revocation_request_code', 'revoked_at']);
        });
    }
};
