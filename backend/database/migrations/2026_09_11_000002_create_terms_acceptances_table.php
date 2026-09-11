<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Evidencia de aceptación de T&C (append-only, polimórfica).
 *
 * DDL equivalente para producción (NO ejecutar migrate en prod):
 * database/scripts/2026/09/DDL_create_terms_versions_and_acceptances.sql
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('terms_acceptances', function (Blueprint $table) {
            $table->id();
            $table->foreignId('terms_version_id')
                ->constrained('terms_versions')
                ->cascadeOnUpdate()
                ->restrictOnDelete();
            $table->unsignedBigInteger('user_id')->index()->comment('FK users.id — quién aceptó');
            $table->bigInteger('company_id')->index()->comment('FK companies.id');
            $table->morphs('acceptable'); // acceptable_type + acceptable_id (+ índice)
            $table->string('consent_scope', 60)
                ->default('MATICERTS_TERMS_IMMEDIATE_EXECUTION')
                ->comment('Alcance del consentimiento (App\\Enums\\TermsConsentScopeEnum)');
            $table->dateTime('accepted_at')->comment('Fecha/hora de aceptación (UTC, servidor)');
            $table->string('ip_address', 45)->comment('IP del cliente, capturada en servidor');
            $table->string('user_agent', 512)->nullable();
            $table->timestamps();

            $table->unique(['acceptable_type', 'acceptable_id', 'consent_scope'], 'uq_terms_acceptances_target_scope');
            $table->foreign('user_id', 'fk_terms_acceptances_user')
                ->references('id')->on('users')
                ->cascadeOnUpdate()
                ->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('terms_acceptances');
    }
};
