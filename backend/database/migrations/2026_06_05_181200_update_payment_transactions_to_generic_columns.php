<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Renombrar columnas acopladas a Wompi a nombres genéricos en payment_transactions.
 *
 * Ejecución segura (solo esta migración):
 * php artisan migrate --path=database/migrations/2026_06_05_181200_update_payment_transactions_to_generic_columns.php
 */
return new class extends Migration
{
    public function up(): void
    {
        // Renombrar columnas de Wompi a genéricas
        DB::statement('ALTER TABLE payment_transactions CHANGE wompi_transaction_id provider_transaction_id varchar(100) NULL');
        DB::statement('ALTER TABLE payment_transactions CHANGE wompi_reference provider_reference varchar(100) NULL');
        DB::statement('ALTER TABLE payment_transactions CHANGE wompi_raw_response provider_raw_response longtext NULL');
        DB::statement('ALTER TABLE payment_transactions CHANGE amount_in_cents amount bigint unsigned NOT NULL');

        // Agregar columna payment_provider si no existe
        Schema::table('payment_transactions', function (Blueprint $table) {
            if (!Schema::hasColumn('payment_transactions', 'payment_provider')) {
                $table->string('payment_provider', 50)->after('certificate_order_id')->default('WOMPI');
            }
        });
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE payment_transactions CHANGE provider_transaction_id wompi_transaction_id varchar(100) NULL');
        DB::statement('ALTER TABLE payment_transactions CHANGE provider_reference wompi_reference varchar(100) NULL');
        DB::statement('ALTER TABLE payment_transactions CHANGE provider_raw_response wompi_raw_response longtext NULL');
        DB::statement('ALTER TABLE payment_transactions CHANGE amount provider_transaction_amount bigint unsigned NOT NULL');

        Schema::table('payment_transactions', function (Blueprint $table) {
            $table->dropColumn('payment_provider');
        });
    }
};
