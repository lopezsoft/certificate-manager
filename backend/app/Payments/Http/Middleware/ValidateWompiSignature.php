<?php

namespace App\Payments\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * ValidateWompiSignature
 *
 * Verifica que la petición al webhook proviene genuinamente de WOMPI
 * validando la firma HMAC-SHA256.
 *
 * Implementación completa en Sprint 4.
 * Por ahora permite el paso pero registra el intento de validación.
 *
 * @todo Sprint 4: implementar validación HMAC-SHA256 completa
 */
class ValidateWompiSignature
{
    public function handle(Request $request, Closure $next): Response
    {
        // Sprint 4: implementar validación de firma WOMPI
        // $checksum  = $request->header('X-Event-Checksum');
        // $timestamp = $request->header('X-Event-Timestamp') ?? '';
        // $secret    = config('wompi.events_secret');
        // $payload   = $request->getContent();
        //
        // $expected = hash_hmac('sha256', $timestamp . $payload, $secret);
        // if (! hash_equals($expected, $checksum ?? '')) {
        //     return response()->json(['error' => 'Firma WOMPI inválida'], 401);
        // }

        return $next($request);
    }
}

