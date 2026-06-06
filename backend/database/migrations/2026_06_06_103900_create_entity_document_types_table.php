<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

/**
 * Crea la tabla de tipos de documento constitutivo de entidad.
 *
 * Permite distinguir el flujo de verificación Viafirma:
 * - Cámara de Comercio → enlace biométrico instantáneo
 * - Personería Jurídica → contacto por email
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('entity_document_types', function (Blueprint $table) {
            $table->increments('id');
            $table->string('code', 10)->unique()->comment('Código corto (CC, PJ, etc.)');
            $table->string('description', 120)->comment('Descripción legible');
            $table->boolean('active')->default(true);
        });

        // Seed inicial
        DB::table('entity_document_types')->insert([
            ['id' => 1, 'code' => 'CC', 'description' => 'Cámara de Comercio', 'active' => true],
            ['id' => 2, 'code' => 'PJ', 'description' => 'Personería Jurídica', 'active' => true],
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('entity_document_types');
    }
};
