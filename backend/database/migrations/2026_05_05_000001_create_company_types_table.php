<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Catálogo puro de tipos de empresa (3NF).
 *
 * Independiente de pricing, cuotas o cualquier otra entidad.
 * Ejemplos: API_DEVELOPER, PORTAL_ALLY, ERP_PARTNER
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('company_types', function (Blueprint $table) {
            $table->id();
            $table->string('code', 50)->unique()->comment('Código normalizado: API_DEVELOPER, PORTAL_ALLY, ERP_PARTNER');
            $table->string('name', 100)->comment('Nombre legible');
            $table->text('description')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('company_types');
    }
};
