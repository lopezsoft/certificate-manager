<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Middleware que restringe el acceso a rutas exclusivas de administradores.
 *
 * Verifica el accessor `is_admin` del modelo User (basado en type_id).
 */
class EnsureUserIsAdmin
{
    /**
     * Handle an incoming request.
     *
     * @param \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response) $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (!$user || !$user->is_admin) {
            return response()->json([
                'success' => false,
                'message' => 'Se requieren permisos de administrador.',
            ], 403);
        }

        return $next($request);
    }
}

