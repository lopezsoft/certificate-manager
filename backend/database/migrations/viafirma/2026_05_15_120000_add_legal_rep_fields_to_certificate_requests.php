<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Migración ADITIVA y NO DESTRUCTIVA — §3.0.3 del roadmap.
 *
 * Agrega columnas estructuradas para el Representante Legal en perfiles PJ que
 * vayan por el nuevo flujo Viafirma PKCS#10. Todas las columnas son `nullable`
 * → 100% compatible con los registros históricos.
 *
 * NUNCA se ejecuta con `migrate` global. Sólo vía:
 *   php artisan viafirma:migrate 2026_05_15_120000_add_legal_rep_fields_to_certificate_requests.php --apply
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('certificate_requests', function (Blueprint $t) {
            $t->foreignId('legal_rep_identity_document_id')
                ->nullable()
                ->after('legal_representative')
                ->constrained('identity_documents')
                ->nullOnDelete();
            $t->string('legal_rep_identity_number', 32)->nullable()->after('legal_rep_identity_document_id');
            $t->string('legal_rep_email', 150)->nullable()->after('legal_rep_identity_number');
        });
    }

    public function down(): void
    {
        Schema::table('certificate_requests', function (Blueprint $t) {
            $t->dropConstrainedForeignId('legal_rep_identity_document_id');
            $t->dropColumn(['legal_rep_identity_number', 'legal_rep_email']);
        });
    }
};

