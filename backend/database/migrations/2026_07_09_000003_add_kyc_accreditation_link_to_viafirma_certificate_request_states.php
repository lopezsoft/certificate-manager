<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('viafirma_certificate_request_states', function (Blueprint $table) {
            $table->string('kyc_accreditation_link', 500)->nullable()
                ->after('auto_redownload_attempts')
                ->comment('Link del portal KYC de acreditación (GET /services/accreditation/{codRequest}), capturado la primera vez que remote_status = accreditation.');
        });
    }

    public function down(): void
    {
        Schema::table('viafirma_certificate_request_states', function (Blueprint $table) {
            $table->dropColumn('kyc_accreditation_link');
        });
    }
};
