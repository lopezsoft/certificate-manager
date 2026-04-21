<?php

namespace App\Payments\Http\Middleware;

use App\Payments\Services\WompiPaymentService;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

/**
 * ValidateWompiSignature — Sprint 4
 *
 * Valida la firma HMAC-SHA256 de eventos WOMPI.
 * Rechaza cualquier petición que no provenga genuinamente de WOMPI.
 */
class ValidateWompiSignature
{
    public function __construct(
        private readonly WompiPaymentService $wompiService,
    ) {}

    public function handle(Request $request, Closure $next): Response
    {
        $checksum  = $request->header('X-Event-Checksum', '');
        $timestamp = $request->header('X-Event-Timestamp', '');
        $payload   = $request->getContent();

        if (empty($checksum)) {
            Log::warning('[WOMPI-WEBHOOK] Petición sin checksum — rechazada.');
            return response()->json(['error' => 'Firma WOMPI requerida'], 401);
        }

        $isValid = $this->wompiService->validateWebhookSignature($payload, $checksum, $timestamp);

        if (! $isValid) {
            Log::warning('[WOMPI-WEBHOOK] Firma WOMPI inválida — rechazada.');
            return response()->json(['error' => 'Firma WOMPI inválida'], 401);
        }

        return $next($request);
    }
}
