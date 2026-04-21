<?php

namespace App\Providers;

use App\Payments\Contracts\PaymentGatewayContract;
use App\Payments\Services\WompiPaymentService;
use Illuminate\Support\ServiceProvider;

/**
 * WompiServiceProvider
 *
 * Registra WompiPaymentService con sus parámetros de configuración en el IoC container.
 */
class WompiServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(WompiPaymentService::class, function () {
            return new WompiPaymentService(
                apiUrl:       config('wompi.api_url') ?? 'https://sandbox.wompi.co/v1',
                publicKey:    config('wompi.public_key') ?? '',
                privateKey:   config('wompi.private_key') ?? '',
                eventsSecret: config('wompi.events_secret') ?? '',
                integrityKey: config('wompi.integrity_key') ?? '',
            );
        });

        $this->app->bind(PaymentGatewayContract::class, WompiPaymentService::class);
    }

    public function boot(): void {}
}

