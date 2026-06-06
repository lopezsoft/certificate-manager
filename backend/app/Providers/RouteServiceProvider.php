<?php

namespace App\Providers;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Foundation\Support\Providers\RouteServiceProvider as ServiceProvider;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Route;

class RouteServiceProvider extends ServiceProvider
{
    /**
     * The path to the "home" route for your application.
     *
     * This is used by Laravel authentication to redirect users after login.
     *
     * @var string
     */
    public const HOME = '/dashboard';

    /**
     * The controller namespace for the application.
     *
     * When present, controller route declarations will automatically be prefixed with this namespace.
     *
     * @var string|null
     */
    // protected $namespace = 'App\\Http\\Controllers';
    protected $namespace = 'App\Http\Controllers';

    /**
     * Define your route model bindings, pattern filters, etc.
     *
     * @return void
     */
    public function boot()
    {
        $this->configureRateLimiting();

        $this->routes(function () {
            Route::prefix('api')
                ->middleware('api')
                ->namespace($this->namespace)
                ->group(base_path('routes/api.php'));

            Route::middleware('web')
                ->namespace($this->namespace)
                ->group(base_path('routes/web.php'));
        });
    }

    /**
     * Configure the rate limiters for the application.
     *
     * @return void
     */
    protected function configureRateLimiting()
    {
        // Límite general de la API
        RateLimiter::for('api', function (Request $request) {
            return Limit::perMinute(60)->by(optional($request->user())->id ?: $request->ip());
        });

        // Subida de archivos: máximo 20 por minuto por usuario
        RateLimiter::for('file-upload', function (Request $request) {
            return Limit::perMinute(20)
                ->by(optional($request->user())->id ?: $request->ip())
                ->response(function () {
                    return response()->json([
                        'success' => false,
                        'message' => 'Demasiadas solicitudes de carga de archivos. Intente nuevamente en un minuto.',
                    ], 429);
                });
        });

        // Creación de solicitudes de certificado: máximo 10 por minuto por usuario
        RateLimiter::for('certificate-create', function (Request $request) {
            return Limit::perMinute(10)
                ->by(optional($request->user())->id ?: $request->ip())
                ->response(function () {
                    return response()->json([
                        'success' => false,
                        'message' => 'Demasiadas solicitudes de certificado. Intente nuevamente en un minuto.',
                    ], 429);
                });
        });

        // Emisión de certificados: máximo 5 por minuto por usuario
        RateLimiter::for('certificate-issue', function (Request $request) {
            return Limit::perMinute(5)
                ->by(optional($request->user())->id ?: $request->ip())
                ->response(function () {
                    return response()->json([
                        'success' => false,
                        'message' => 'Demasiadas solicitudes de emisión. Intente nuevamente en un minuto.',
                    ], 429);
                });
        });

        // Creación de Personal Access Tokens: máximo PAT_MAX_PER_DAY por día por usuario
        RateLimiter::for('token-create', function (Request $request) {
            $maxPerDay = config('tokens.max_per_day', 10);
            return Limit::perDay($maxPerDay)
                ->by(optional($request->user())->id ?: $request->ip())
                ->response(function () use ($maxPerDay) {
                    return response()->json([
                        'success' => false,
                        'message' => "Límite de creación de tokens alcanzado. Máximo {$maxPerDay} por día.",
                    ], 429);
                });
        });
    }
}
