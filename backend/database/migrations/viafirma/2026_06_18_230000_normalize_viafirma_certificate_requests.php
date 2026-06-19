<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Normalización de viafirma_certificate_requests.
 *
 * Divide la tabla monolítica en dos tablas con responsabilidades claras:
 *
 *   viafirma_certificate_requests       → Identidad del trámite (15 columnas)
 *   viafirma_certificate_request_states → Ciclo de vida y estado (25 columnas)
 *
 * La migración copia todos los datos existentes antes de eliminar columnas.
 * El rollback restaura las columnas y copia los datos de vuelta.
 */
return new class extends Migration
{
    public function up(): void
    {
        // ── 1. Crear tabla de estados ────────────────────────────────────────
        Schema::create('viafirma_certificate_request_states', function (Blueprint $table) {
            $table->id();

            $table->unsignedBigInteger('viafirma_certificate_request_id')
                ->unique()
                ->comment('FK 1:1 con viafirma_certificate_requests.id');

            // Estado FSM
            $table->string('internal_state', 32)->default('DRAFT')
                ->comment('Estado interno del FSM de Certificate Manager');
            $table->string('remote_status', 64)->nullable()
                ->comment('Estado remoto devuelto por Viafirma RA');

            // Criptografía y almacenamiento
            $table->string('key_vault_ref', 128)->default('')
                ->comment('Referencia al vault de la llave privada RSA');
            $table->string('csr_fingerprint', 64)->default('')
                ->comment('SHA-256 del CSR generado');
            $table->text('csr_pem')->nullable()
                ->comment('CSR en formato PEM (sensible — oculto en serialización)');
            $table->string('p7b_storage_path', 255)->nullable()
                ->comment('Ruta en storage del archivo P7B descargado de Viafirma');
            $table->string('p12_storage_path', 255)->nullable()
                ->comment('Ruta en storage del archivo P12 ensamblado');
            $table->string('p12_password_ref', 255)->nullable()
                ->comment('Referencia al vault del PIN del P12 (sensible)');

            // Polling
            $table->smallInteger('poll_attempts')->unsigned()->default(0)
                ->comment('Número de veces que se ha consultado el estado remoto');
            $table->timestamp('next_poll_at')->nullable()
                ->comment('Próxima ejecución programada de PollViafirmaStatusJob');
            $table->timestamp('last_polled_at')->nullable()
                ->comment('Última vez que se consultó el estado remoto');

            // Payloads (JSON)
            $table->longText('request_payload')->nullable()
                ->charset('utf8mb4')->collation('utf8mb4_bin')
                ->comment('Payload enviado a Viafirma RA al crear el trámite');
            $table->longText('last_status_response')->nullable()
                ->charset('utf8mb4')->collation('utf8mb4_bin')
                ->comment('Última respuesta de estado recibida de Viafirma RA');

            // Timestamps del ciclo de vida
            $table->timestamp('submitted_at')->nullable()
                ->comment('Cuando se envió el CSR a Viafirma RA');
            $table->timestamp('downloaded_at')->nullable()
                ->comment('Cuando se descargó el P7B de Viafirma');
            $table->timestamp('assembled_at')->nullable()
                ->comment('Cuando se ensambló el P12');
            $table->timestamp('expires_at')->nullable()
                ->comment('Fecha de expiración del certificado emitido');

            // Errores
            $table->string('last_error_code', 64)->nullable()
                ->comment('Código del último error registrado');
            $table->text('last_error_message')->nullable()
                ->comment('Mensaje del último error registrado');

            // Revocación
            $table->string('revocation_request_code', 255)->nullable()
                ->comment('Código devuelto por Viafirma al revocar el certificado');
            $table->timestamp('revoked_at')->nullable()
                ->comment('Timestamp en que se ejecutó la revocación exitosamente');

            // Re-descarga automática
            $table->unsignedTinyInteger('auto_redownload_attempts')->nullable()->default(0)
                ->comment('Contador de intentos automáticos de re-descarga. Máximo 5 antes de requerir intervención manual');

            $table->timestamps();

            // ── Índices ──────────────────────────────────────────────────────
            // Nombre corto para FK (MySQL limita a 64 chars)
            $table->foreign('viafirma_certificate_request_id', 'vcrs_vcr_id_foreign')
                ->references('id')
                ->on('viafirma_certificate_requests')
                ->onDelete('cascade');

            $table->index(['internal_state', 'next_poll_at'],
                'vcrs_internal_state_next_poll_at_index');
            $table->index('internal_state', 'vcrs_internal_state_index');
            $table->index('remote_status', 'vcrs_remote_status_index');
            $table->index('next_poll_at', 'vcrs_next_poll_at_index');
        });

        // ── 2. Copiar datos existentes ───────────────────────────────────────
        DB::statement("
            INSERT INTO viafirma_certificate_request_states (
                viafirma_certificate_request_id,
                internal_state,
                remote_status,
                key_vault_ref,
                csr_fingerprint,
                csr_pem,
                p7b_storage_path,
                p12_storage_path,
                p12_password_ref,
                poll_attempts,
                next_poll_at,
                last_polled_at,
                request_payload,
                last_status_response,
                submitted_at,
                downloaded_at,
                assembled_at,
                expires_at,
                last_error_code,
                last_error_message,
                revocation_request_code,
                revoked_at,
                auto_redownload_attempts,
                created_at,
                updated_at
            )
            SELECT
                id,
                internal_state,
                remote_status,
                key_vault_ref,
                csr_fingerprint,
                csr_pem,
                p7b_storage_path,
                p12_storage_path,
                p12_password_ref,
                poll_attempts,
                next_poll_at,
                last_polled_at,
                request_payload,
                last_status_response,
                submitted_at,
                downloaded_at,
                assembled_at,
                expires_at,
                last_error_code,
                last_error_message,
                revocation_request_code,
                revoked_at,
                auto_redownload_attempts,
                created_at,
                updated_at
            FROM viafirma_certificate_requests
        ");

        // ── 3. Eliminar columnas de estado de la tabla principal ─────────────
        Schema::table('viafirma_certificate_requests', function (Blueprint $table) {
            // Eliminar índices primero
            $table->dropIndex('viafirma_certificate_requests_internal_state_next_poll_at_index');
            $table->dropIndex('viafirma_certificate_requests_internal_state_index');
            $table->dropIndex('viafirma_certificate_requests_remote_status_index');
            $table->dropIndex('viafirma_certificate_requests_next_poll_at_index');

            // Eliminar columnas de estado
            $table->dropColumn([
                'internal_state',
                'remote_status',
                'key_vault_ref',
                'csr_fingerprint',
                'csr_pem',
                'p7b_storage_path',
                'p12_storage_path',
                'p12_password_ref',
                'request_payload',
                'last_status_response',
                'poll_attempts',
                'next_poll_at',
                'last_polled_at',
                'submitted_at',
                'downloaded_at',
                'assembled_at',
                'expires_at',
                'last_error_code',
                'last_error_message',
                'revocation_request_code',
                'revoked_at',
                'auto_redownload_attempts',
            ]);
        });
    }

    public function down(): void
    {
        // ── 1. Restaurar columnas en la tabla principal ──────────────────────
        Schema::table('viafirma_certificate_requests', function (Blueprint $table) {
            $table->string('internal_state', 32)->default('DRAFT')->after('validity_days');
            $table->string('remote_status', 64)->nullable()->after('internal_state');
            $table->string('key_vault_ref', 128)->default('')->after('remote_status');
            $table->string('csr_fingerprint', 64)->default('')->after('key_vault_ref');
            $table->text('csr_pem')->nullable()->after('csr_fingerprint');
            $table->string('p7b_storage_path', 255)->nullable()->after('csr_pem');
            $table->string('p12_storage_path', 255)->nullable()->after('p7b_storage_path');
            $table->string('p12_password_ref', 255)->nullable()->after('p12_storage_path');
            $table->longText('request_payload')->nullable()->charset('utf8mb4')->collation('utf8mb4_bin')->after('p12_password_ref');
            $table->longText('last_status_response')->nullable()->charset('utf8mb4')->collation('utf8mb4_bin')->after('request_payload');
            $table->smallInteger('poll_attempts')->unsigned()->default(0)->after('last_status_response');
            $table->timestamp('next_poll_at')->nullable()->after('poll_attempts');
            $table->timestamp('last_polled_at')->nullable()->after('next_poll_at');
            $table->timestamp('submitted_at')->nullable()->after('last_polled_at');
            $table->timestamp('downloaded_at')->nullable()->after('submitted_at');
            $table->timestamp('assembled_at')->nullable()->after('downloaded_at');
            $table->timestamp('expires_at')->nullable()->after('assembled_at');
            $table->string('last_error_code', 64)->nullable()->after('expires_at');
            $table->text('last_error_message')->nullable()->after('last_error_code');
            $table->string('revocation_request_code', 255)->nullable()->after('last_error_message');
            $table->timestamp('revoked_at')->nullable()->after('revocation_request_code');
            $table->unsignedTinyInteger('auto_redownload_attempts')->nullable()->default(0)->after('revoked_at');
        });

        // ── 2. Restaurar datos desde la tabla de estados ─────────────────────
        DB::statement("
            UPDATE viafirma_certificate_requests vcr
            INNER JOIN viafirma_certificate_request_states vcrs
                ON vcrs.viafirma_certificate_request_id = vcr.id
            SET
                vcr.internal_state           = vcrs.internal_state,
                vcr.remote_status            = vcrs.remote_status,
                vcr.key_vault_ref            = vcrs.key_vault_ref,
                vcr.csr_fingerprint          = vcrs.csr_fingerprint,
                vcr.csr_pem                  = vcrs.csr_pem,
                vcr.p7b_storage_path         = vcrs.p7b_storage_path,
                vcr.p12_storage_path         = vcrs.p12_storage_path,
                vcr.p12_password_ref         = vcrs.p12_password_ref,
                vcr.request_payload          = vcrs.request_payload,
                vcr.last_status_response     = vcrs.last_status_response,
                vcr.poll_attempts            = vcrs.poll_attempts,
                vcr.next_poll_at             = vcrs.next_poll_at,
                vcr.last_polled_at           = vcrs.last_polled_at,
                vcr.submitted_at             = vcrs.submitted_at,
                vcr.downloaded_at            = vcrs.downloaded_at,
                vcr.assembled_at             = vcrs.assembled_at,
                vcr.expires_at               = vcrs.expires_at,
                vcr.last_error_code          = vcrs.last_error_code,
                vcr.last_error_message       = vcrs.last_error_message,
                vcr.revocation_request_code  = vcrs.revocation_request_code,
                vcr.revoked_at               = vcrs.revoked_at,
                vcr.auto_redownload_attempts = vcrs.auto_redownload_attempts
        ");

        // ── 3. Restaurar índices ─────────────────────────────────────────────
        Schema::table('viafirma_certificate_requests', function (Blueprint $table) {
            $table->index(['internal_state', 'next_poll_at'],
                'viafirma_certificate_requests_internal_state_next_poll_at_index');
            $table->index('internal_state',
                'viafirma_certificate_requests_internal_state_index');
            $table->index('remote_status',
                'viafirma_certificate_requests_remote_status_index');
            $table->index('next_poll_at',
                'viafirma_certificate_requests_next_poll_at_index');
        });

        // ── 4. Eliminar tabla de estados ─────────────────────────────────────
        Schema::dropIfExists('viafirma_certificate_request_states');
    }
};
