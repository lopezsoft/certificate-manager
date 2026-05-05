<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Agrega FK company_type_id y flag has_agreement a companies.
 *
 * Enlaza cada empresa con su tipo (catálogo company_types)
 * y permite indicar si tiene un convenio especial (POSTPAID).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('companies', function (Blueprint $table) {
            $table->foreignId('company_type_id')
                  ->nullable()
                  ->after('id')
                  ->constrained('company_types')
                  ->nullOnDelete();
            $table->boolean('has_agreement')
                  ->default(false)
                  ->after('company_type_id')
                  ->comment('Indica si la empresa tiene convenio POSTPAID');
        });
    }

    public function down(): void
    {
        Schema::table('companies', function (Blueprint $table) {
            $table->dropForeign(['company_type_id']);
            $table->dropColumn(['company_type_id', 'has_agreement']);
        });
    }
};
