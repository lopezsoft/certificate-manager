<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Override granular del proveedor de emisión de certificados por empresa.
 *
 * NULL  → usar default global (config('certificate.issuance.default_provider'))
 * 'mail'     → forzar flujo legacy por correo electrónico
 * 'viafirma' → forzar flujo PKCS#10 Zero-Touch
 * (futuros) → 'andes_scd', 'gse', etc.
 *
 * El override es leído por CertificateIssuanceProviderFactory::resolveFor().
 *
 * Ver plan: docs/2026-05-19-15-00-PLAN-UNIFICACION-API-V1-Y-PROVEEDOR-AGNOSTICO-VIAFIRMA.md
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('companies', function (Blueprint $table) {
            $table->string('issuance_provider', 32)
                  ->nullable()
                  ->after('has_agreement')
                  ->comment('Override del proveedor de emisión: mail|viafirma|null=default');

            $table->index('issuance_provider', 'idx_companies_issuance_provider');
        });
    }

    public function down(): void
    {
        Schema::table('companies', function (Blueprint $table) {
            $table->dropIndex('idx_companies_issuance_provider');
            $table->dropColumn('issuance_provider');
        });
    }
};

