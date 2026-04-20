<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * CREATE TABLE payment_transactions — Registro de transacciones WOMPI por orden.
 * Tabla 100% nueva. No modifica tablas existentes.
 *
 * Ejecución segura:
 * php artisan migrate --path=database/migrations/2026_04_21_000009_create_payment_transactions_table.php
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payment_transactions', function (Blueprint $table) {
            $table->id();

            $table->unsignedBigInteger('certificate_order_id')
                ->comment('FK a certificate_orders.id');

            $table->string('wompi_transaction_id', 100)
                ->nullable()
                ->comment('ID único de la transacción en WOMPI');

            $table->string('wompi_reference', 100)
                ->nullable()
                ->comment('Referencia del comercio enviada a WOMPI');

            $table->string('status', 20)
                ->default('PENDING')
                ->comment('PENDING | APPROVED | DECLINED | VOIDED | ERROR');

            $table->unsignedBigInteger('amount_in_cents')
                ->comment('Monto total en centavos (WOMPI trabaja en centavos)');

            $table->string('currency', 3)
                ->default('COP');

            $table->string('payment_method_type', 50)
                ->nullable()
                ->comment('Tipo de medio de pago devuelto por WOMPI');

            $table->json('wompi_raw_response')
                ->nullable()
                ->comment('Respuesta completa de WOMPI (para auditoría)');

            $table->string('acceptance_token', 500)
                ->nullable()
                ->comment('Token de aceptación de T&C usado en la transacción');

            $table->timestamp('paid_at')
                ->nullable()
                ->comment('Momento en que WOMPI confirmó el pago (APPROVED)');

            $table->timestamps();

            $table->foreign('certificate_order_id')
                ->references('id')
                ->on('certificate_orders')
                ->onDelete('restrict');

            $table->index('wompi_transaction_id');
            $table->index('wompi_reference');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payment_transactions');
    }
};

