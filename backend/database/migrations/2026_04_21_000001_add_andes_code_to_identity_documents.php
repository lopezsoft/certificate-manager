<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * ALTER TABLE identity_documents — SOLO agrega columna andes_code (nullable).
 * No modifica, renombra ni elimina nada preexistente.
 *
 * Ejecución segura:
 * php artisan migrate --path=database/migrations/2026_04_21_000001_add_andes_code_to_identity_documents.php
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('identity_documents', function (Blueprint $table) {
            $table->unsignedInteger('andes_code')
                ->nullable()
                ->after('code')
                ->comment('Código ANDES ID para TipoDoc: 1=CC, 2=NIT, 3=CE, 6=Pasaporte');
        });
    }

    public function down(): void
    {
        Schema::table('identity_documents', function (Blueprint $table) {
            $table->dropColumn('andes_code');
        });
    }
};

