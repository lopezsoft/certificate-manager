<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('certificate_requests', function (Blueprint $table) {
            $table->integer('country_id')
                ->default(45)
                ->after('company_id')
                ->comment('ID del país (FK countries). Default: 45 = Colombia');

            $table->foreign('country_id', 'fk_cr_country')
                ->references('id')
                ->on('countries')
                ->onUpdate('cascade')
                ->onDelete('restrict');

            // Agregar índice para búsquedas por país
            $table->index('country_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('certificate_requests', function (Blueprint $table) {
            $table->dropForeign('fk_cr_country');
            $table->dropIndex('certificate_requests_country_id_index');
            $table->dropColumn('country_id');
        });
    }
};
