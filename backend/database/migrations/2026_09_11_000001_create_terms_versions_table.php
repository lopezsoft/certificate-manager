<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Historial de versiones publicadas de los Términos y Condiciones.
 *
 * DDL equivalente para producción (NO ejecutar migrate en prod):
 * database/scripts/2026/09/DDL_create_terms_versions_and_acceptances.sql
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('terms_versions', function (Blueprint $table) {
            $table->id();
            $table->string('version', 20)->unique()->comment('Etiqueta de versión (ej. 1.0, 1.1)');
            $table->dateTime('published_at')->comment('Fecha/hora de publicación (UTC)');
            $table->string('source_url', 255)->comment('URL pública del documento al publicar');
            $table->char('content_hash', 64)->comment('SHA-256 del texto normalizado');
            $table->longText('content')->comment('Snapshot íntegro del texto aceptado (evidencia)');
            $table->boolean('is_current')->default(false)->index()->comment('1 = versión vigente');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('terms_versions');
    }
};
