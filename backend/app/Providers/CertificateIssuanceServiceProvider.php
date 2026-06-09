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
        // IMPORTANTE: usar $app->make('log') en vez de Log::getFacadeRoot()
        // para evitar la re-resolución del facade en queue:work daemon mode
        // (resetScope() llama clearResolvedInstances() al inicio de cada iteración,
        // lo que hace que getFacadeRoot() añada frames extra al stack de PHP,
        // causando stack overflow en Windows con la cadena completa de DI).
        $this->app->singleton('certificate.issuance.logger', function ($app): LoggerInterface {
            $channel = (string) config('certificate.issuance.log_channel', '');
            /** @var \Illuminate\Log\LogManager $logManager */
            $logManager = $app->make('log');
            return $channel !== '' ? $logManager->channel($channel) : $logManager;
        });

        // Factory explícita (no auto-wire) para evitar stack overflow en
        // queue:work daemon mode en Windows (php.ini stack más pequeño).
        $this->app->singleton(CertificateIssuanceProviderFactory::class, function ($app): CertificateIssuanceProviderFactory {
            return new CertificateIssuanceProviderFactory(
                container: $app,
                logger:    $app->make('certificate.issuance.logger'),
            );
        });

        $this->app->singleton(CertificateIssuanceOrchestrator::class, function ($app): CertificateIssuanceOrchestrator {
            return new CertificateIssuanceOrchestrator(
                factory: $app->make(CertificateIssuanceProviderFactory::class),
                logger:  $app->make('certificate.issuance.logger'),
            );
        });

        // Inyectar el logger compartido en los proveedores restantes.
        $this->app->when([
                MailIssuanceProvider::class,
                ViafirmaIssuanceProvider::class,
            ])
            ->needs(LoggerInterface::class)
            ->give(fn () => $this->app->make('certificate.issuance.logger'));

        // Singleton EXPLÍCITO para ViafirmaIssuanceProvider.
        // ⚠️  Windows / WAMP: el autowiring (singleton sin closure) usa reflection
        // y provoca stack overflow silencioso en la cadena profunda de DI.
        // La factory closure resuelve cada dep de forma plana (sin recursión).
        $this->app->singleton(ViafirmaIssuanceProvider::class, function ($app): ViafirmaIssuanceProvider {
            return new ViafirmaIssuanceProvider(
                useCase:    $app->make(\App\Modules\Viafirma\Application\UseCases\IssueCertificateUseCase::class),
                repository: $app->make(\App\Modules\Viafirma\Domain\Contracts\ViafirmaCertificateRequestRepositoryContract::class),
                logger:     $app->make('certificate.issuance.logger'),
            );
        });
        $this->app->singleton(MailIssuanceProvider::class);
    }
}

