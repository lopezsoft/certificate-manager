<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Hace user_id nullable en change_histories para soportar cambios
     * de estado generados por el sistema (jobs, crons) sin usuario asociado.
     * En estos casos user_of_change = 'SYSTEM'.
     */
    public function up(): void
    {
        Schema::table('change_histories', function (Blueprint $table) {
            $table->unsignedBigInteger('user_id')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('change_histories', function (Blueprint $table) {
            $table->unsignedBigInteger('user_id')->nullable(false)->change();
        });
    }
};
