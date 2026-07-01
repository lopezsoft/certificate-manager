<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * ADD COLUMN vigencia TO certificate_order_items
 *
 * Agrega la columna 'vigencia' para rastrear la vigencia (1 o 2 años) de cada item comprado.
 * Esto permite validar que la vigencia solicitada en una solicitud de certificado coincida
 * con los cupos disponibles.
 *
 * Ejecución segura:
 * php artisan migrate --path=database/migrations/2026_07_01_000000_add_vigencia_to_certificate_order_items_table.php
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('certificate_order_items', function (Blueprint $table) {
            $table->unsignedTinyInteger('vigencia')
                ->default(1)
                ->comment('Vigencia en años: 1 o 2')
                ->after('certificate_request_id');
        });
    }

    public function down(): void
    {
        Schema::table('certificate_order_items', function (Blueprint $table) {
            $table->dropColumn('vigencia');
        });
    }
};
