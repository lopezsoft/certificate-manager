<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Tabla principal del nuevo flujo PKCS#10 — §3.1 del roadmap.
 *
 * Es un agregado satélite 1:1 sobre `certificate_requests`: NO duplica
 * datos del solicitante (se resuelven vía relaciones).
 *
 * Ejecutar SIEMPRE manualmente:
 *   php artisan viafirma:migrate 2026_05_15_120001_create_viafirma_certificate_requests_table.php --apply
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('viafirma_certificate_requests', function (Blueprint $t) {
            $t->id();

            // ── Enlace fuerte al agregado de negocio existente ──────────────
            $t->foreignId('certificate_request_id')
                ->unique()
                ->constrained('certificate_requests')
                ->cascadeOnDelete();
            $t->foreignId('company_id')->constrained()->cascadeOnDelete();
            $t->foreignId('requested_by_user_id')->nullable()->constrained('users')->nullOnDelete();

            // ── Identificadores Viafirma ────────────────────────────────────
            $t->string('cod_request', 32)->nullable()->unique()->index();
            $t->string('public_id', 64)->nullable()->index();
            $t->string('cod_profile')->nullable();
            $t->string('ra_code', 32);

            // ── Datos específicos del trámite Viafirma ──────────────────────
            $t->enum('profile_type', ['FE_PJ', 'FE_PN'])->index();
            $t->enum('identity_type', ['IDC', 'PAS'])->default('IDC');
            $t->string('country_code', 2)->default('CO');
            $t->string('organization_type', 16)->nullable();
            $t->unsignedSmallInteger('validity_days')->default(730);

            // ── Estado de la máquina (FSM interna + estado remoto) ─────────
            $t->string('internal_state', 32)->default('DRAFT')->index();
            $t->string('remote_status', 64)->nullable()->index();

            // ── Criptografía (referencias, NUNCA el material) ───────────────
            $t->string('key_vault_ref', 128);
            $t->string('csr_fingerprint', 64);
            $t->text('csr_pem')->nullable();
            $t->string('p7b_storage_path')->nullable();
            $t->string('p12_storage_path')->nullable();
            $t->string('p12_password_ref')->nullable();

            // ── Payload original y respuesta (auditoría / snapshot) ─────────
            $t->json('request_payload')->nullable();
            $t->json('last_status_response')->nullable();

            // ── Polling control ─────────────────────────────────────────────
            $t->unsignedSmallInteger('poll_attempts')->default(0);
            $t->timestamp('next_poll_at')->nullable()->index();
            $t->timestamp('last_polled_at')->nullable();
            $t->timestamp('submitted_at')->nullable();
            $t->timestamp('downloaded_at')->nullable();
            $t->timestamp('assembled_at')->nullable();
            $t->timestamp('expires_at')->nullable();

            // ── Errores ─────────────────────────────────────────────────────
            $t->string('last_error_code', 64)->nullable();
            $t->text('last_error_message')->nullable();

            $t->timestamps();
            $t->softDeletes();

            $t->index(['internal_state', 'next_poll_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('viafirma_certificate_requests');
    }
};

