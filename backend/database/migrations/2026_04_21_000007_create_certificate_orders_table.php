<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * CREATE TABLE certificate_orders — Órdenes de compra PREPAID de certificados vía WOMPI.
 * Tabla 100% nueva. No modifica tablas existentes.
 *
 * Ejecución segura:
 * php artisan migrate --path=database/migrations/2026_04_21_000007_create_certificate_orders_table.php
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('certificate_orders', function (Blueprint $table) {
            $table->id();

            $table->unsignedBigInteger('company_id')
                ->comment('FK a companies.id');

            $table->unsignedBigInteger('user_id')
                ->comment('FK a users.id — usuario que generó la orden');

            $table->unsignedInteger('quantity')
                ->comment('Cantidad de certificados comprados');

            $table->unsignedTinyInteger('vigencia')
                ->comment('Vigencia en años: 1 o 2');

            $table->unsignedBigInteger('unit_price')
                ->comment('Precio unitario en COP (sin IVA)');

            $table->unsignedBigInteger('subtotal')
                ->comment('quantity × unit_price — sin IVA');

            $table->unsignedBigInteger('tax_amount')
                ->comment('IVA (19%) en COP');

            $table->unsignedBigInteger('total_amount')
                ->comment('subtotal + tax_amount — total a cobrar');

            $table->string('currency', 3)
                ->default('COP');

            $table->string('status', 20)
                ->default('PENDING')
                ->comment('PENDING | PAID | FAILED | REFUNDED');

            $table->string('payment_method', 30)
                ->nullable()
                ->comment('CARD | NEQUI | PSE | BANCOLOMBIA_TRANSFER');

            $table->string('wompi_reference', 100)
                ->nullable()
                ->unique()
                ->comment('Referencia única enviada a WOMPI');

            $table->timestamps();

            $table->foreign('company_id')
                ->references('id')
                ->on('companies')
                ->onDelete('restrict');

            $table->foreign('user_id')
                ->references('id')
                ->on('users')
                ->onDelete('restrict');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('certificate_orders');
    }
};

