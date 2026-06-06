<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

/**
 * ValidateSyncSignature — Autenticación servicio-a-servicio.
 *
 * Valida peticiones de sincronización desde sistemas externos (ERP, API)
 * usando un esquema de API Key + HMAC-SHA256 + Timestamp.
 *
 * Headers requeridos:
 *   - X-Sync-Key:       API Key del sistema origen
 *   - X-Sync-Signature: HMAC-SHA256(payload + timestamp, secret)
 *   - X-Sync-Timestamp: Unix timestamp (ventana de 5 minutos)
 */
class ValidateSyncSignature
{
    /** Ventana de validez del timestamp en segundos (5 minutos) */
    private const TIMESTAMP_TOLERANCE = 300;

    public function handle(Request $request, Closure $next): Response
    {
        // 1. Validar API Key
        $apiKey = $request->header('X-Sync-Key', '');
        $configKey = config('services.sync.api_key');

        if (empty($configKey) || !hash_equals($configKey, $apiKey)) {
            Log::warning('[SYNC] API Key inválida.', ['ip' => $request->ip()]);
            return response()->json(['error' => 'API Key inválida'], Response::HTTP_UNAUTHORIZED);
        }

        // 2. Validar Timestamp (protección contra replay attacks)
        $timestamp = (int) $request->header('X-Sync-Timestamp', '0');

        if (abs(time() - $timestamp) > self::TIMESTAMP_TOLERANCE) {
            Log::warning('[SYNC] Timestamp expirado.', [
                'ip'        => $request->ip(),
                'timestamp' => $timestamp,
                'server'    => time(),
            ]);
            return response()->json(['error' => 'Request expirado'], Response::HTTP_UNAUTHORIZED);
        }

        // 3. Validar HMAC-SHA256
        $payload   = $request->getContent();
        $signature = $request->header('X-Sync-Signature', '');
        $secret    = config('services.sync.api_secret');
        $expected  = hash_hmac('sha256', $payload . $timestamp, $secret);

        if (!hash_equals($expected, $signature)) {
            Log::warning('[SYNC] Firma HMAC inválida.', ['ip' => $request->ip()]);
            return response()->json(['error' => 'Firma inválida'], Response::HTTP_UNAUTHORIZED);
        }

        // 4. Validar IP (opcional — solo si se configuran IPs permitidas)
        $allowedIps = config('services.sync.allowed_ips', []);

        if (!empty($allowedIps) && !in_array($request->ip(), $allowedIps, true)) {
            Log::warning('[SYNC] IP no autorizada.', ['ip' => $request->ip()]);
            return response()->json(['error' => 'IP no autorizada'], Response::HTTP_FORBIDDEN);
        }

        Log::info('[SYNC] Petición autenticada correctamente.', ['ip' => $request->ip()]);

        return $next($request);
    }
}
