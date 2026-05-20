<?php

declare(strict_types=1);

namespace App\Providers;

use App\Services\Certificate\CertificateIssuanceOrchestrator;
use App\Services\Certificate\CertificateIssuanceProviderFactory;
use App\Services\Certificate\Providers\MailIssuanceProvider;
use App\Services\Certificate\Providers\ViafirmaIssuanceProvider;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\ServiceProvider;
use Psr\Log\LoggerInterface;

/**
 * Wiring DI para el subsistema de emisión agnóstica de certificados.
 *
 * Mantiene los bindings simples y centralizados (SOLID-D + factory),
 * sin tocar el wiring del módulo Viafirma (que sigue en
 * {@see ViafirmaServiceProvider}).
 */
final class CertificateIssuanceServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // Logger dedicado: reutiliza el canal de logging principal pero
        // permite redirigirlo en el futuro mediante config.
        $this->app->singleton('certificate.issuance.logger', function (): LoggerInterface {
            $channel = (string) config('certificate.issuance.log_channel', '');
            /** @var LoggerInterface $logger */
            $logger = $channel !== '' ? Log::channel($channel) : Log::getFacadeRoot();
            return $logger;
        });

        // Inyectar el logger compartido en los servicios del subsistema.
        $this->app->when([
                CertificateIssuanceProviderFactory::class,
                CertificateIssuanceOrchestrator::class,
                MailIssuanceProvider::class,
                ViafirmaIssuanceProvider::class,
            ])
            ->needs(LoggerInterface::class)
            ->give(fn () => $this->app->make('certificate.issuance.logger'));

        $this->app->singleton(CertificateIssuanceProviderFactory::class);
        $this->app->singleton(CertificateIssuanceOrchestrator::class);
    }
}

