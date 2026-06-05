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
        $payload   = $request->getContent();
        $data      = json_decode($payload, true);
        
        $checksum  = $data['signature']['checksum'] ?? '';
        $timestamp = (string) ($data['timestamp'] ?? '');

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
