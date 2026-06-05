<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Agregar columna UUID a certificate_orders para identificación pública.
 *
 * Ejecución segura:
 * php artisan migrate --path=database/migrations/2026_06_05_185300_add_uuid_to_certificate_orders_table.php
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('certificate_orders', function (Blueprint $table) {
            $table->uuid('uuid')->after('id')->unique();
        });

        // Generar UUID para registros existentes
        $orders = DB::table('certificate_orders')->whereNull('uuid')->orWhere('uuid', '')->get();
        foreach ($orders as $order) {
            DB::table('certificate_orders')
                ->where('id', $order->id)
                ->update(['uuid' => (string) \Illuminate\Support\Str::uuid()]);
        }
    }

    public function down(): void
    {
        Schema::table('certificate_orders', function (Blueprint $table) {
            $table->dropColumn('uuid');
        });
    }
};
