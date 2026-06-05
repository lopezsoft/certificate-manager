<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('certificate_orders', function (Blueprint $table) {
            if (!Schema::hasColumn('certificate_orders', 'payment_provider')) {
                $table->string('payment_provider', 50)->after('status')->default('WOMPI');
            }
        });

        DB::statement('ALTER TABLE certificate_orders CHANGE wompi_reference provider_reference varchar(100) NULL');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('certificate_orders', function (Blueprint $table) {
            $table->dropColumn('payment_provider');
        });

        DB::statement('ALTER TABLE certificate_orders CHANGE provider_reference wompi_reference varchar(100) NULL');
    }
};
