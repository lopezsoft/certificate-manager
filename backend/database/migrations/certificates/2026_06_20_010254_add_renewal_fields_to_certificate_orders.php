<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('certificate_orders', function (Blueprint $table) {
            $table->string('order_type', 30)->default('QUOTA_PURCHASE')->after('uuid');
            $table->bigInteger('certificate_request_id')->nullable()->after('company_id');

            $table->foreign('certificate_request_id')
                ->references('id')
                ->on('certificate_requests')
                ->onDelete('set null');
        });
    }

    public function down(): void
    {
        Schema::table('certificate_orders', function (Blueprint $table) {
            $table->dropForeign(['certificate_request_id']);
            $table->dropColumn(['order_type', 'certificate_request_id']);
        });
    }
};
