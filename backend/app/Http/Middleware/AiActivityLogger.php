<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

class AiActivityLogger
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $startTime = microtime(true);
        
        // Log the start of AI-related activity
        Log::info('AI Activity Started', [
            'user_id' => auth()->id(),
            'ip' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'url' => $request->fullUrl(),
            'method' => $request->method(),
            'timestamp' => now()->toISOString()
        ]);

        $response = $next($request);

        $executionTime = microtime(true) - $startTime;

        // Log the completion of AI-related activity
        Log::info('AI Activity Completed', [
            'user_id' => auth()->id(),
            'ip' => $request->ip(),
            'url' => $request->fullUrl(),
            'method' => $request->method(),
            'status_code' => $response->getStatusCode(),
            'execution_time' => round($executionTime, 4),
            'timestamp' => now()->toISOString()
        ]);

        return $response;
    }
}
