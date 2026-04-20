<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * CREATE TABLE certificate_quotas — Cupos asignados por Admin LOPEZSOFT a empresas aliadas.
 * Tabla 100% nueva. No modifica tablas existentes.
 *
 * Ejecución segura:
 * php artisan migrate --path=database/migrations/2026_04_21_000006_create_certificate_quotas_table.php
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('certificate_quotas', function (Blueprint $table) {
            $table->id();

            $table->unsignedBigInteger('company_id')
                ->comment('FK a companies.id — empresa beneficiaria del cupo');

            $table->unsignedInteger('allocated_quantity')
                ->default(0)
                ->comment('Cantidad de certificados asignados en el período');

            $table->unsignedInteger('used_quantity')
                ->default(0)
                ->comment('Cantidad de certificados ya consumidos');

            $table->date('period_start')
                ->comment('Inicio del período del cupo');

            $table->date('period_end')
                ->comment('Fin del período del cupo');

            $table->string('status', 20)
                ->default('ACTIVE')
                ->comment('ACTIVE | EXHAUSTED | EXPIRED');

            $table->string('billing_type', 20)
                ->default('POSTPAID')
                ->comment('POSTPAID — siempre (admin asigna cupo mensual)');

            $table->unsignedBigInteger('assigned_by')
                ->comment('FK a users.id — admin LOPEZSOFT que asignó el cupo');

            $table->text('notes')
                ->nullable()
                ->comment('Notas internas del admin');

            $table->timestamps();

            $table->foreign('company_id')
                ->references('id')
                ->on('companies')
                ->onDelete('restrict');

            $table->foreign('assigned_by')
                ->references('id')
                ->on('users')
                ->onDelete('restrict');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('certificate_quotas');
    }
};

