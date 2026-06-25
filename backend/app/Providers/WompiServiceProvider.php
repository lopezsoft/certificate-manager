<?php

namespace App\Providers;

use App\Payments\Contracts\PaymentGatewayContract;
use App\Payments\Services\WompiPaymentService;
use Illuminate\Support\ServiceProvider;

/**
 * WompiServiceProvider
 *
 * Registra WompiPaymentService con sus parámetros de configuración en el IoC container.
 *
 * FAIL-FAST: En entorno de producción, las claves WOMPI_PUBLIC_KEY y
 * WOMPI_PRIVATE_KEY son obligatorias. Si faltan, se lanza RuntimeException
 * al instanciar el servicio para evitar fallos silenciosos en runtime.
 */
class WompiServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(WompiPaymentService::class, function () {
            $publicKey    = (string) (config('wompi.public_key') ?? '');
            $privateKey   = (string) (config('wompi.private_key') ?? '');
            $eventsSecret = (string) (config('wompi.events_secret') ?? '');
            $integrityKey = (string) (config('wompi.integrity_key') ?? '');
            $apiUrl       = (string) (config('wompi.api_url') ?? 'https://sandbox.wompi.co/v1');

            // Fail-fast en producción: las claves no pueden estar vacías.
            if (app()->environment('production') && ($publicKey === '' || $privateKey === '')) {
                throw new \RuntimeException(
                    'WompiServiceProvider: WOMPI_PUBLIC_KEY y WOMPI_PRIVATE_KEY son obligatorios en producción. '
                    . 'Revisa el archivo .env del servidor.'
                );
            }

            return new WompiPaymentService(
                apiUrl:       $apiUrl,
                publicKey:    $publicKey,
                privateKey:   $privateKey,
                eventsSecret: $eventsSecret,
                integrityKey: $integrityKey,
            );
        });

        $this->app->bind(PaymentGatewayContract::class, WompiPaymentService::class);
    }

    public function boot(): void {}
}
