<?php

namespace App\Payments\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Payments\Jobs\ProcessWompiWebhookJob;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

/**
 * WompiWebhookController — Sprint 4
 * Recibe eventos de WOMPI (firma validada por ValidateWompiSignature middleware).
 * Delega el procesamiento asíncrono a ProcessWompiWebhookJob.
 */
class WompiWebhookController extends Controller
{
    public function handle(Request $request): JsonResponse
    {
        $event = $request->all();

        Log::info('[WOMPI-WEBHOOK] Evento recibido.', [
            'event'     => $event['event'] ?? 'unknown',
            'timestamp' => $event['timestamp'] ?? null,
        ]);

        // Delegar al job (async) — responder 200 inmediatamente a WOMPI
        ProcessWompiWebhookJob::dispatch($event);

        return response()->json(['message' => 'OK'], 200);
    }
}
