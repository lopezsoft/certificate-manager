<?php

declare(strict_types=1);

namespace App\Modules\Viafirma\Presentation\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Feature Gate para el módulo Viafirma PKCS#10 (V-508).
 *
 * Permite rollout gradual vía config/env:
 *  - VIAFIRMA_PKCS10_ENABLED=true   → activo para todos
 *  - VIAFIRMA_PKCS10_ENABLED=false  → retorna 503 con mensaje claro
 *
 * En producción se combinará con rollout por company_id (percentil).
 *
 * Registrar en routes: Route::middleware('viafirma.feature_gate')
 */
final class ViafirmaFeatureGate
{
    public function handle(Request $request, Closure $next): Response
    {
        $enabled = (bool) config('viafirma.feature_flag.enabled', true);

        if (!$enabled) {
            return response()->json([
                'success' => false,
                'message' => 'El módulo de certificados digitales Viafirma está temporalmente deshabilitado. Intente más tarde.',
                'code'    => 'VIAFIRMA_DISABLED',
            ], 503);
        }

        // Rollout gradual por company_id (opcional)
        $rolloutPercentage = (int) config('viafirma.feature_flag.rollout_percentage', 100);
        if ($rolloutPercentage < 100) {
            $user = $request->user();
            $companyId = $user?->company_id ?? 0;

            // Hash determinístico del company_id para asignación consistente
            $hash = crc32("viafirma_rollout_{$companyId}");
            $bucket = abs($hash) % 100;

            if ($bucket >= $rolloutPercentage) {
                return response()->json([
                    'success' => false,
                    'message' => 'El módulo de certificados digitales aún no está disponible para su empresa. Será habilitado gradualmente.',
                    'code'    => 'VIAFIRMA_ROLLOUT_PENDING',
                ], 503);
            }
        }

        return $next($request);
    }
}
