<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * CREATE TABLE andes_certificate_requests — Extensión 1:1 de certificate_requests para ANDES.
 * Tabla 100% nueva. No modifica tablas existentes.
 *
 * Ejecución segura:
 * php artisan migrate --path=database/migrations/2026_04_21_000004_create_andes_certificate_requests_table.php
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('andes_certificate_requests', function (Blueprint $table) {
            $table->id();

            $table->unsignedBigInteger('certificate_request_id')
                ->unique()
                ->comment('FK a certificate_requests.id — relación 1:1');

            $table->string('andes_solicitud_id', 100)
                ->nullable()
                ->comment('ID de solicitud devuelto por ANDES PKI tras emitir');

            $table->unsignedInteger('tipo_cert')
                ->comment('10=P.Jurídica, 11=P.Natural');

            $table->unsignedInteger('formato')
                ->comment('2=Token físico, 3=PKCS10, 4=Token virtual');

            $table->unsignedInteger('vigencia_cert')
                ->comment('3=1año, 4=2años, 15=1día, 17=14meses');

            $table->string('andes_estado', 50)
                ->nullable()
                ->comment('Estado devuelto por ANDES');

            $table->text('andes_message')
                ->nullable()
                ->comment('Mensaje descriptivo de ANDES');

            $table->json('andes_raw_response')
                ->nullable()
                ->comment('Respuesta SOAP completa (serializada)');

            $table->string('pin_hash', 255)
                ->nullable()
                ->comment('Hash bcrypt del PIN asignado (nunca en texto plano)');

            $table->string('certificate_serial', 100)
                ->nullable()
                ->comment('Número serial del certificado emitido');

            $table->timestamp('emitted_at')
                ->nullable()
                ->comment('Fecha/hora de emisión del certificado');

            $table->timestamp('revoked_at')
                ->nullable()
                ->comment('Fecha/hora de revocación (null = activo)');

            $table->timestamps();

            $table->foreign('certificate_request_id')
                ->references('id')
                ->on('certificate_requests')
                ->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('andes_certificate_requests');
    }
};

