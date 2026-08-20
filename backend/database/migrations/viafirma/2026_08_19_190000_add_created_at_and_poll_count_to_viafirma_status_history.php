<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Agrega `created_at` (fijo, momento en que inicia el episodio de estado) y
 * `poll_count_in_state` (cuántas veces se confirmó sin cambios) a
 * viafirma_status_history.
 *
 * Contexto: StateMachine::transition() dejó de insertar una fila por cada poll
 * cuando el estado no cambia (evita crecimiento sin control ahora que se
 * eliminó la expiración automática del polling). En su lugar, actualiza la
 * fila vigente con `occurred_at` (última confirmación) e incrementa
 * `poll_count_in_state`. `created_at` nunca se toca tras el INSERT inicial,
 * permitiendo calcular `occurred_at - created_at` = tiempo real en el estado,
 * y `poll_count_in_state` permite detectar polling degradado (pocas
 * confirmaciones para el tiempo transcurrido).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('viafirma_status_history', function (Blueprint $table) {
            $table->timestamp('created_at')
                ->useCurrent()
                ->after('attempt_number')
                ->comment('Momento en que inicia el episodio de estado. No se actualiza tras el INSERT.');

            $table->unsignedInteger('poll_count_in_state')
                ->default(1)
                ->after('created_at')
                ->comment('Cantidad de polls que confirmaron este mismo estado sin cambios.');
        });

        // Backfill: usar occurred_at como mejor aproximación de created_at para
        // filas ya existentes (bajo el comportamiento anterior, cada fila era
        // un INSERT individual por poll, no un episodio agrupado).
        DB::statement('UPDATE viafirma_status_history SET created_at = occurred_at');
    }

    public function down(): void
    {
        Schema::table('viafirma_status_history', function (Blueprint $table) {
            $table->dropColumn(['created_at', 'poll_count_in_state']);
        });
    }
};
