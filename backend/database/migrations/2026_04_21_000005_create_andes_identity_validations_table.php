<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * CREATE TABLE andes_identity_validations — Historial de validaciones de identidad ANDES ID.
 * Tabla 100% nueva. No modifica tablas existentes.
 *
 * Ejecución segura:
 * php artisan migrate --path=database/migrations/2026_04_21_000005_create_andes_identity_validations_table.php
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('andes_identity_validations', function (Blueprint $table) {
            $table->id();

            $table->unsignedBigInteger('andes_certificate_request_id')
                ->comment('FK a andes_certificate_requests.id');

            $table->string('validation_type', 20)
                ->comment('OTP (PhoneSelection) | EXAM (ShowExam)');

            $table->string('token', 500)
                ->comment('Token de sesión devuelto por ANDES ID /solicitud_inicial');

            $table->tinyInteger('estado')
                ->default(0)
                ->comment('-1=No encontrado, 0=En curso, 1=Validado, 2=Fallido');

            $table->json('questions_data')
                ->nullable()
                ->comment('Preguntas del cuestionario (ShowExam)');

            $table->json('raw_response')
                ->nullable()
                ->comment('Respuesta completa de ANDES ID');

            $table->unsignedSmallInteger('attempts')
                ->default(0)
                ->comment('Número de intentos realizados');

            $table->timestamp('validated_at')
                ->nullable()
                ->comment('Momento en que se alcanzó estado=1');

            $table->timestamp('expires_at')
                ->nullable()
                ->comment('Expiración del token ANDES (1h desde emisión)');

            $table->timestamps();

            $table->foreign('andes_certificate_request_id')
                ->references('id')
                ->on('andes_certificate_requests')
                ->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('andes_identity_validations');
    }
};

