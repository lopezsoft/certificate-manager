<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * ALTER TABLE type_organization — SOLO agrega columna andes_cert_type (nullable).
 * No modifica, renombra ni elimina nada preexistente.
 *
 * Ejecución segura:
 * php artisan migrate --path=database/migrations/2026_04_21_000002_add_andes_cert_type_to_type_organization.php
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('type_organization', function (Blueprint $table) {
            $table->unsignedInteger('andes_cert_type')
                ->nullable()
                ->comment('tipoCert ANDES: 10=Facturación P.Jurídica, 11=Facturación P.Natural');
        });
    }

    public function down(): void
    {
        Schema::table('type_organization', function (Blueprint $table) {
            $table->dropColumn('andes_cert_type');
        });
    }
};

