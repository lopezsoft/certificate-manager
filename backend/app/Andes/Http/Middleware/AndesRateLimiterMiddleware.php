<?php

namespace App\Andes\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Symfony\Component\HttpFoundation\Response;

/**
 * AndesRateLimiterMiddleware
 *
 * Limita las llamadas a endpoints ANDES ID para evitar:
 * - Agotar el cupo de validaciones del contrato ANDES
 * - Ataques de fuerza bruta en verificación OTP
 *
 * Límites:
 * - start:         3 intentos / 10 minutos por empresa
 * - verify-otp:    5 intentos / 10 minutos por validation_id
 * - resend-otp:    2 intentos / 5 minutos por validation_id
 */
class AndesRateLimiterMiddleware
{
    public function handle(Request $request, Closure $next, string $action = 'generic'): Response
    {
        $userId = $request->user()?->id ?? $request->ip();
        $key    = "andes:{$action}:{$userId}";

        [$maxAttempts, $decaySeconds] = match($action) {
            'start'      => [3,  600],  // 3 por 10 min
            'verify-otp' => [5,  600],  // 5 por 10 min
            'resend-otp' => [2,  300],  // 2 por 5 min
            default      => [10, 60],
        };

        if (RateLimiter::tooManyAttempts($key, $maxAttempts)) {
            $seconds = RateLimiter::availableIn($key);
            return response()->json([
                'message' => "Demasiados intentos. Intente de nuevo en {$seconds} segundos.",
                'retry_after' => $seconds,
            ], 429);
        }

        RateLimiter::hit($key, $decaySeconds);

        return $next($request);
    }
}

