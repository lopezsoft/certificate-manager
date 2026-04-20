<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * ALTER TABLE certificate_requests — SOLO agrega provider_type y andes_request_number.
 * No modifica, renombra ni elimina nada preexistente.
 *
 * Ejecución segura:
 * php artisan migrate --path=database/migrations/2026_04_21_000003_add_provider_columns_to_certificate_requests.php
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('certificate_requests', function (Blueprint $table) {
            $table->string('provider_type', 20)
                ->default('CAMERFIRMA')
                ->after('request_status')
                ->comment('Proveedor del certificado: CAMERFIRMA | ANDES');

            $table->string('andes_request_number', 100)
                ->nullable()
                ->after('provider_type')
                ->comment('Número de solicitud devuelto por ANDES PKI');
        });
    }

    public function down(): void
    {
        Schema::table('certificate_requests', function (Blueprint $table) {
            $table->dropColumn(['provider_type', 'andes_request_number']);
        });
    }
};

