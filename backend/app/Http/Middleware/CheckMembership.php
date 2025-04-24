<?php

namespace App\Http\Middleware;

use App\Modules\Memberships\MembershipManager;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CheckMembership
{
    /**
     * Handle an incoming request.
     *
     * @param \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response) $next
     * @throws \Exception
     */
    public function handle(Request $request, Closure $next): Response
    {
        $response   = MembershipManager::getActive($request);
        $query      = $response->query;
        if($query) {
            if (!$query->isActive) {
                return response()->json([
                    'message' => 'Tu membresía ha expirado. Renueva ahora y sigue disfrutando de todos los beneficios que MATIAS APP tiene para ti.',
                ], 403);
            }
        }
        return $next($request);
    }
}
