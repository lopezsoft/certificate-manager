<?php

namespace App\Providers;

use App\Andes\Contracts\AndesIdentityServiceContract;
use App\Andes\Contracts\AndesPkiServiceContract;
use App\Andes\Services\AndesHealthCheckService;
use App\Andes\Services\AndesIdentityService;
use App\Andes\Services\AndesPkiService;
use App\Andes\Services\AndesSoapClientFactory;
use App\Andes\Services\AndesTokenManager;
use Illuminate\Support\ServiceProvider;

/**
 * AndesServiceProvider
 *
 * Registra los servicios del módulo ANDES SCD en el IoC container.
 * AndesTokenManager es singleton para reutilizar el token cacheado entre requests.
 */
class AndesServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // Singleton: garantiza una sola instancia del gestor de tokens por request
        $this->app->singleton(AndesTokenManager::class, function ($app) {
            return new AndesTokenManager(
                apiUrl:   config('andes.id_api_url') ?? '',
                username: config('andes.id_username') ?? '',
                password: config('andes.id_password') ?? '',
                cacheTtl: config('andes.token_cache_ttl') ?? 3300,
            );
        });

        // AndesIdentityService — binding con su contrato
        $this->app->bind(AndesIdentityServiceContract::class, function ($app) {
            return new AndesIdentityService(
                tokenManager: $app->make(AndesTokenManager::class),
                apiUrl:       config('andes.id_api_url'),
            );
        });

        // AndesSoapClientFactory — singleton (WSDL se cachea internamente)
        $this->app->singleton(AndesSoapClientFactory::class, function ($app) {
            return new AndesSoapClientFactory(
                wsdlUrl:  config('andes.pki_wsdl_url') ?? '',
                username: config('andes.pki_username') ?? '',
                password: config('andes.pki_password') ?? '',
            );
        });

        // AndesPkiService — binding con su contrato
        $this->app->bind(AndesPkiServiceContract::class, function ($app) {
            return new AndesPkiService(
                soapFactory: $app->make(AndesSoapClientFactory::class),
            );
        });

        // AndesHealthCheckService — singleton para evitar spam de health checks
        $this->app->singleton(AndesHealthCheckService::class, function ($app) {
            return new AndesHealthCheckService(
                tokenManager: $app->make(AndesTokenManager::class),
                pkiWsdlUrl:   config('andes.pki_wsdl_url') ?? '',
            );
        });
    }

    public function boot(): void {}
}


