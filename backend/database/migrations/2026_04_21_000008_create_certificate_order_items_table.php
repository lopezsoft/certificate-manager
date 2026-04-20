<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * CREATE TABLE certificate_order_items — Ítems individuales de cada orden de compra.
 * Tabla 100% nueva. No modifica tablas existentes.
 *
 * Ejecución segura:
 * php artisan migrate --path=database/migrations/2026_04_21_000008_create_certificate_order_items_table.php
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('certificate_order_items', function (Blueprint $table) {
            $table->id();

            $table->unsignedBigInteger('certificate_order_id')
                ->comment('FK a certificate_orders.id');

            $table->unsignedBigInteger('certificate_request_id')
                ->nullable()
                ->comment('FK a certificate_requests.id — null hasta que se usa el item');

            $table->string('status', 20)
                ->default('PENDING')
                ->comment('PENDING | USED | EXPIRED');

            $table->timestamps();

            $table->foreign('certificate_order_id')
                ->references('id')
                ->on('certificate_orders')
                ->onDelete('cascade');

            $table->foreign('certificate_request_id')
                ->references('id')
                ->on('certificate_requests')
                ->onDelete('set null');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('certificate_order_items');
    }
};

