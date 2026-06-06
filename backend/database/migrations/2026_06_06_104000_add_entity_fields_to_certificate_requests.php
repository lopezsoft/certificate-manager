<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Agrega campos para el flujo Viafirma PJ:
 * - entity_document_type_id: tipo de documento constitutivo (default 1 = Cámara de Comercio)
 * - legal_rep_email: correo del representante legal para contacto Viafirma
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('certificate_requests', function (Blueprint $table) {
            $table->unsignedInteger('entity_document_type_id')
                ->default(1)
                ->after('type_organization_id')
                ->comment('Tipo de documento constitutivo (FK entity_document_types). Default: 1 = Cámara de Comercio');

            $table->string('legal_rep_email', 250)
                ->nullable()
                ->after('legal_representative')
                ->comment('Email del representante legal para verificación Viafirma');

            $table->foreign('entity_document_type_id', 'fk_cr_entity_document_type')
                ->references('id')
                ->on('entity_document_types')
                ->onUpdate('cascade')
                ->onDelete('restrict');
        });
    }

    public function down(): void
    {
        Schema::table('certificate_requests', function (Blueprint $table) {
            $table->dropForeign('fk_cr_entity_document_type');
            $table->dropColumn(['entity_document_type_id', 'legal_rep_email']);
        });
    }
};
