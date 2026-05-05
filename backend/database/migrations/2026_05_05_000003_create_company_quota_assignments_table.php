<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Tabla pivote 3NF: asigna rangos de precios a empresas.
 *
 * Une company + pricing_tier + cuota asignada + tipo de cupo.
 * Cada empresa puede tener una asignación activa a la vez.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('company_quota_assignments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')
                  ->constrained('companies')
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
    }

    public function down(): void
    {
        Schema::dropIfExists('company_quota_assignments');
    }
};
