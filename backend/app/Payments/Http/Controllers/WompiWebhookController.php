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
    /**
     * @OA\Post(
     *     path="/webhooks/wompi",
     *     tags={"Pagos Externos"},
     *     summary="Recibir evento de pago de WOMPI",
     *     description="Endpoint receptor de webhooks de WOMPI. La firma HMAC-SHA256 es validada automáticamente por el middleware ValidateWompiSignature. No requiere autenticación Bearer.",
     *     @OA\RequestBody(required=true, @OA\JsonContent(
     *         @OA\Property(property="event", type="string", example="transaction.updated"),
     *         @OA\Property(property="data", type="object",
     *             @OA\Property(property="transaction", type="object",
     *                 @OA\Property(property="id", type="string"),
     *                 @OA\Property(property="reference", type="string"),
     *                 @OA\Property(property="status", type="string", enum={"PENDING","APPROVED","DECLINED","VOIDED","ERROR"})
     *             )
     *         ),
     *         @OA\Property(property="timestamp", type="integer", example=1745000000),
     *         @OA\Property(property="signature", type="object",
     *             @OA\Property(property="checksum", type="string"),
     *             @OA\Property(property="properties", type="array", @OA\Items(type="string"))
     *         )
     *     )),
     *     @OA\Response(response=200, description="Evento recibido y encolado"),
     *     @OA\Response(response=401, description="Firma HMAC inválida")
     * )
     */
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
