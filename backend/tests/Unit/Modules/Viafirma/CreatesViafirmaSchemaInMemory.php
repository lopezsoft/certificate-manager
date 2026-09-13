<?php

declare(strict_types=1);

namespace Tests\Unit\Modules\Viafirma;

use Illuminate\Support\Facades\Schema;

/**
 * Crea en SQLite `:memory:` sólo las tablas que el código bajo prueba escribe.
 *
 * No toca ninguna base de datos real: el esquema vive en RAM durante el test y
 * desaparece al terminar el proceso. Se usa únicamente donde el código de
 * producción hace un `Model::create()` que no puede interceptarse con mocks
 * (p. ej. `StateMachine::recordHistory()`, que es privado y escribe directo).
 *
 * Para todo lo demás se prefieren mocks / entidades en memoria con
 * `setRelation()`, que no requieren esquema alguno.
 */
trait CreatesViafirmaSchemaInMemory
{
    protected function createViafirmaStatusHistoryTable(): void
    {
        if (Schema::hasTable('viafirma_status_history')) {
            return;
        }

        Schema::create('viafirma_status_history', function ($table) {
            $table->id();
            $table->unsignedBigInteger('viafirma_certificate_request_id')->nullable();
            $table->string('previous_state')->nullable();
            $table->string('new_state')->nullable();
            $table->string('remote_status')->nullable();
            $table->text('raw_response')->nullable();
            $table->integer('attempt_number')->nullable();
            $table->integer('poll_count_in_state')->nullable();
            $table->timestamp('occurred_at')->nullable();
            $table->timestamp('created_at')->nullable();
        });
    }

    /**
     * Tabla mínima de solicitudes. Necesaria sólo donde el código bajo prueba
     * consulta de forma estática (`CertificateRequest::query()->findOrFail()`),
     * que no admite inyección de dobles.
     */
    protected function createCertificateRequestsTable(): void
    {
        if (Schema::hasTable('certificate_requests')) {
            return;
        }

        Schema::create('certificate_requests', function ($table) {
            $table->id();
            $table->unsignedBigInteger('company_id')->nullable();
            $table->unsignedBigInteger('country_id')->nullable();
            $table->unsignedBigInteger('city_id')->nullable();
            $table->unsignedBigInteger('identity_document_id')->nullable();
            $table->unsignedBigInteger('type_organization_id')->nullable();
            $table->unsignedBigInteger('entity_document_type_id')->nullable();
            $table->string('company_name')->nullable();
            $table->string('dni')->nullable();
            $table->string('dv')->nullable();
            $table->string('document_number')->nullable();
            $table->string('address')->nullable();
            $table->string('postal_code')->nullable();
            $table->string('phone')->nullable();
            $table->string('mobile')->nullable();
            $table->string('email')->nullable();
            $table->string('legal_representative')->nullable();
            $table->string('legal_rep_first_name')->nullable();
            $table->string('legal_rep_last_name')->nullable();
            $table->string('legal_rep_email')->nullable();
            $table->string('request_status')->nullable();
            $table->string('base_path')->nullable();
            $table->string('uuid')->nullable();
            $table->integer('life')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    /**
     * Catálogos que `CertificateRequest::query()->with([...])` intenta cargar
     * (el eager-load descarta cualquier relación montada con setRelation).
     */
    protected function createCatalogTables(): void
    {
        if (!Schema::hasTable('identity_documents')) {
            Schema::create('identity_documents', function ($table) {
                $table->id();
                $table->string('code')->nullable();
                $table->string('abbreviation')->nullable();
                $table->string('description')->nullable();
            });
        }

        // Singular por decisión del modelo (TypeOrganization::$table).
        if (!Schema::hasTable('type_organization')) {
            Schema::create('type_organization', function ($table) {
                $table->id();
                $table->string('code')->nullable();
                $table->string('description')->nullable();
            });
        }

        if (!Schema::hasTable('countries')) {
            Schema::create('countries', function ($table) {
                $table->id();
                $table->string('name_country')->nullable();
                $table->string('abbreviation_A2')->nullable();
                $table->string('abbreviation_A3')->nullable();
            });
        }

        if (!Schema::hasTable('departments')) {
            Schema::create('departments', function ($table) {
                $table->id();
                $table->unsignedBigInteger('country_id')->nullable();
                $table->string('name_department')->nullable();
            });
        }

        if (!Schema::hasTable('cities')) {
            Schema::create('cities', function ($table) {
                $table->id();
                $table->unsignedBigInteger('department_id')->nullable();
                $table->string('name_city')->nullable();
            });
        }

        if (!Schema::hasTable('companies')) {
            Schema::create('companies', function ($table) {
                $table->id();
                $table->unsignedBigInteger('country_id')->nullable();
                $table->unsignedBigInteger('city_id')->nullable();
                $table->unsignedBigInteger('identity_document_id')->nullable();
                $table->unsignedBigInteger('type_organization_id')->nullable();
                $table->string('company_name')->nullable();
                $table->string('trade_name')->nullable();
                $table->string('dni')->nullable();
                $table->string('dv')->nullable();
                $table->string('address')->nullable();
                $table->string('postal_code')->nullable();
                $table->string('phone')->nullable();
                $table->string('email')->nullable();
                $table->boolean('active')->default(true);
                $table->timestamps();
            });
        }
    }

    /**
     * Tablas del trámite Viafirma (entidad + su estado normalizado). Sólo
     * donde el código consulta de forma estática y no admite dobles.
     */
    protected function createViafirmaRequestTables(): void
    {
        if (!Schema::hasTable('viafirma_certificate_requests')) {
            Schema::create('viafirma_certificate_requests', function ($table) {
                $table->id();
                $table->unsignedBigInteger('certificate_request_id')->nullable();
                $table->unsignedBigInteger('company_id')->nullable();
                $table->string('cod_request')->nullable();
                $table->string('public_id')->nullable();
                $table->string('cod_profile')->nullable();
                $table->string('ra_code')->nullable();
                $table->timestamps();
                $table->softDeletes();
            });
        }

        if (!Schema::hasTable('viafirma_certificate_request_states')) {
            Schema::create('viafirma_certificate_request_states', function ($table) {
                $table->id();
                $table->unsignedBigInteger('viafirma_certificate_request_id')->nullable();
                $table->string('internal_state')->nullable();
                $table->string('remote_status')->nullable();
                $table->integer('poll_attempts')->nullable()->default(0);
                $table->integer('auto_redownload_attempts')->nullable()->default(0);
                $table->string('last_error_code')->nullable();
                $table->text('last_error_message')->nullable();
                $table->text('kyc_accreditation_link')->nullable();
                $table->timestamp('kyc_flow_completed_at')->nullable();
                $table->timestamp('kyc_last_call_sent_at')->nullable();
                $table->string('key_vault_ref')->nullable();
                $table->string('p12_password_ref')->nullable();
                $table->text('csr_pem')->nullable();
                $table->string('p7b_storage_path')->nullable();
                $table->string('p12_storage_path')->nullable();
                $table->timestamp('submitted_at')->nullable();
                $table->timestamp('next_poll_at')->nullable();
                $table->timestamps();
            });
        }
    }

    protected function createChangeHistoriesTable(): void
    {
        if (Schema::hasTable('change_histories')) {
            return;
        }

        Schema::create('change_histories', function ($table) {
            $table->id();
            $table->unsignedBigInteger('certificate_request_id')->nullable();
            $table->string('status')->nullable();
            $table->text('comments')->nullable();
            $table->string('user_of_change')->nullable();
            $table->unsignedBigInteger('user_id')->nullable();
            $table->timestamps();
        });
    }
}
