<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 1. Agregar columna user_type_id con FK
        Schema::table('pricing_tiers', function (Blueprint $table) {
            $table->smallInteger('user_type_id')
                ->nullable()
                ->after('code')
                ->comment('Tipo de usuario al que pertenece esta lista de precios');

            $table->foreign('user_type_id')
                ->references('id')
                ->on('user_types')
                ->onUpdate('cascade')
                ->onDelete('restrict');

            $table->index('user_type_id');
        });

        // 2. Poblar la relación según la distribución definida
        // INCLUDED → ADMINISTRADOR (id=1)
        DB::table('pricing_tiers')
            ->where('code', 'INCLUDED')
            ->update(['user_type_id' => 1]);

        // PARTNER_RANGE_* → PARTNER (id=4)
        DB::table('pricing_tiers')
            ->where('code', 'like', 'PARTNER_RANGE_%')
            ->update(['user_type_id' => 4]);

        // SOFTWARE_HOUSE → CASA DE SOFTWARE (id=2)
        DB::table('pricing_tiers')
            ->where('code', 'SOFTWARE_HOUSE')
            ->update(['user_type_id' => 2]);

        // SERVER_RANGE_* → ARRENDAMIENTO EN SERVIDOR (id=3)
        DB::table('pricing_tiers')
            ->where('code', 'like', 'SERVER_RANGE_%')
            ->update(['user_type_id' => 3]);

        // 3. Hacer la columna NOT NULL después de poblarla
        Schema::table('pricing_tiers', function (Blueprint $table) {
            $table->smallInteger('user_type_id')->nullable(false)->change();
        });
    }

    public function down(): void
    {
        Schema::table('pricing_tiers', function (Blueprint $table) {
            $table->dropForeign(['user_type_id']);
            $table->dropIndex(['user_type_id']);
            $table->dropColumn('user_type_id');
        });
    }
};
