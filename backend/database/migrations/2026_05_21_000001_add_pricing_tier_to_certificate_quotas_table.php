<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Agrega pricing_tier_id a certificate_quotas y elimina company_quota_assignments.
 *
 * Unifica el modelo de cuotas: certificate_quotas es la única tabla de cupos.
 * La columna pricing_tier_id vincula cada cupo con su rango de precio.
 *
 * Ejecución segura (individual):
 * php artisan migrate --path=database/migrations/2026_05_21_000001_add_pricing_tier_to_certificate_quotas_table.php
 */
return new class extends Migration
{
    public function up(): void
    {
        // 1. Agregar FK pricing_tier_id a certificate_quotas
        Schema::table('certificate_quotas', function (Blueprint $table) {
            $table->foreignId('pricing_tier_id')
                  ->nullable()
                  ->after('company_id')
                  ->constrained('pricing_tiers')
                  ->nullOnDelete();
        });

        // 2. Eliminar tabla huérfana (nunca tuvo datos)
        Schema::dropIfExists('company_quota_assignments');
    }

    public function down(): void
    {
        // 1. Restaurar company_quota_assignments
        Schema::create('company_quota_assignments', function (Blueprint $table) {
            $table->id();
            $table->bigInteger('company_id');
            $table->foreign('company_id')
                  ->references('id')->on('companies')
                  ->cascadeOnDelete();
            $table->foreignId('pricing_tier_id')
                  ->constrained('pricing_tiers')
                  ->restrictOnDelete();
            $table->string('billing_type', 20)->default('PREPAID')
                  ->comment('PREPAID o POSTPAID');
            $table->integer('allocated_quantity')->default(0)
                  ->comment('Certificados de cuota asignados');
            $table->integer('used_quantity')->default(0)
                  ->comment('Certificados consumidos');
            $table->date('period_start')->nullable();
            $table->date('period_end')->nullable();
            $table->boolean('is_active')->default(true);
            $table->text('notes')->nullable();
            $table->foreignId('assigned_by')
                  ->nullable()
                  ->constrained('users')
                  ->nullOnDelete();
            $table->timestamps();
            $table->index(['company_id', 'is_active']);
        });

        // 2. Quitar pricing_tier_id de certificate_quotas
        Schema::table('certificate_quotas', function (Blueprint $table) {
            $table->dropForeign(['pricing_tier_id']);
            $table->dropColumn('pricing_tier_id');
        });
    }
};
