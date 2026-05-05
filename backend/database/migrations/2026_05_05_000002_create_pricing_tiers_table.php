<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Catálogo agnóstico de rangos de precios (3NF).
 *
 * Solo almacena rangos y precios, sin definir a quién pertenece.
 * La asignación de rangos a empresas se maneja en company_quota_assignments.
 *
 * Montos en valor real COP (DECIMAL), NO en centavos.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pricing_tiers', function (Blueprint $table) {
            $table->id();
            $table->string('code', 50)->unique()->comment('Ej: RANGO_1, RANGO_2, RANGO_3');
            $table->string('name', 100)->comment('Nombre legible: Básico, Profesional, Enterprise');
            $table->integer('min_quantity')->comment('Cantidad mínima del rango');
            $table->integer('max_quantity')->nullable()->comment('NULL = sin límite superior');
            $table->decimal('price_1yr', 12, 2)->comment('Precio por certificado vigencia 1 año (COP)');
            $table->decimal('price_2yr', 12, 2)->comment('Precio por certificado vigencia 2 años (COP)');
            $table->boolean('is_active')->default(true);
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pricing_tiers');
    }
};
