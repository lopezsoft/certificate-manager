<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Tabla auxiliar de trazabilidad técnica fina de la FSM remota Viafirma — §3.2.
 *
 * Complementa (NO reemplaza) `change_histories` que mantiene la auditoría
 * de negocio. Esta guarda granularidad de polling + raw responses.
 *
 *   php artisan viafirma:migrate 2026_05_15_120002_create_viafirma_status_history_table.php --apply
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('viafirma_status_history', function (Blueprint $t) {
            $t->id();
            $t->foreignId('viafirma_certificate_request_id')
                ->constrained('viafirma_certificate_requests')
                ->cascadeOnDelete();
            $t->string('previous_state', 32)->nullable();
            $t->string('new_state', 32);
            $t->string('remote_status', 64)->nullable();
            $t->json('raw_response')->nullable();
            $t->unsignedInteger('attempt_number')->default(0);
            $t->timestamp('occurred_at')->useCurrent();
            $t->index(['viafirma_certificate_request_id', 'occurred_at'], 'viafirma_status_history_req_occ_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('viafirma_status_history');
    }
};

