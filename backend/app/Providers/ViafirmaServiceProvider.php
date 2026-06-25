<?php

declare(strict_types=1);

namespace App\Providers;

use App\Modules\Viafirma\Domain\Contracts\CryptoServiceContract;
use App\Modules\Viafirma\Domain\Contracts\KeyVault;
use App\Modules\Viafirma\Domain\Contracts\ViafirmaClient;
use App\Modules\Viafirma\Infrastructure\Crypto\OpenSslCryptoService;
use App\Modules\Viafirma\Infrastructure\Http\GuzzleViafirmaClient;
use App\Modules\Viafirma\Infrastructure\Http\OAuth1Signer;
use App\Modules\Viafirma\Infrastructure\Http\ProfileResponseParser;
use App\Modules\Viafirma\Infrastructure\KeyVault\EncryptedLocalKeyVault;
use App\Modules\Viafirma\Domain\Contracts\ViafirmaCertificateRequestRepositoryContract;
use App\Modules\Viafirma\Infrastructure\Logging\SafePemLogger;
use App\Modules\Viafirma\Infrastructure\Persistence\ViafirmaCertificateRequestRepository;
use GuzzleHttp\Client as GuzzleClient;
use GuzzleHttp\ClientInterface;
use Illuminate\Contracts\Encryption\Encrypter;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\ServiceProvider;
use Psr\Log\LoggerInterface;

/**
 * ViafirmaServiceProvider
 *
 * Registra el wiring del módulo Viafirma siguiendo DIP:
 *  - CryptoServiceContract → OpenSslCryptoService
 *  - KeyVault              → EncryptedLocalKeyVault (driver `encrypted_local`)
 *  - ViafirmaClient        → GuzzleViafirmaClient   (configurable)
 *  - LoggerInterface (#viafirma) → SafePemLogger (decorator del canal config)
 *
 * IMPORTANTE: Ningún singleton valida credenciales al registrarse.
 * La validación de VIAFIRMA_CLIENT_ID / VIAFIRMA_CLIENT_SECRET es LAZY
 * (se ejecuta en OAuth1Signer::ensureCredentialsConfigured() al firmar),
 * para no bloquear comandos de introspección (route:list, config:cache)
 * ni requests HTTP que no usen el módulo Viafirma.
 *
 * No registra migraciones a propósito (política §10.bis del roadmap): éstas
 * se ejecutan SIEMPRE individualmente vía `viafirma:migrate {file}`.
 */
final class ViafirmaServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // ---- Logger decorado ----------------------------------------------------
        // IMPORTANTE: usar $app->make('log') en vez de Log::getFacadeRoot()
        // para evitar stack overflow en queue:work daemon mode en Windows.
        $this->app->singleton(SafePemLogger::class, function ($app): SafePemLogger {
            $channel = config('viafirma.logging.channel');
            /** @var \Illuminate\Log\LogManager $logManager */
            $logManager = $app->make('log');
            $base = $channel ? $logManager->channel($channel) : $logManager;
            /** @var LoggerInterface $base */
            return new SafePemLogger($base);
        });

        // ---- Crypto -------------------------------------------------------------
        $this->app->singleton(OpenSslCryptoService::class, function (): OpenSslCryptoService {
            return new OpenSslCryptoService(
                digestAlg:   (string) config('viafirma.crypto.digest_alg', 'sha256'),
                opensslConf: config('viafirma.crypto.openssl_conf') ?: null,
            );
        });
        $this->app->bind(CryptoServiceContract::class, OpenSslCryptoService::class);

        // ---- KeyVault (driver) --------------------------------------------------
        $this->app->singleton(KeyVault::class, function ($app): KeyVault {
            $driver = (string) config('viafirma.crypto.key_vault_driver', 'encrypted_local');
            return match ($driver) {
                'encrypted_local' => new EncryptedLocalKeyVault(
                    disk: Storage::disk((string) config('viafirma.crypto.disk', config('certificate.storage.disk', 'local'))),
                    crypt: $app->make(Encrypter::class),
                    vaultPath: (string) config('viafirma.crypto.vault_path', 'viafirma/vault'),
                ),
                'aws_kms' => $app->make(\App\Modules\Viafirma\Infrastructure\KeyVault\AwsKmsKeyVault::class),
                default => throw new \RuntimeException(
                    "Driver de KeyVault no soportado: '{$driver}'"
                ),
            };
        });

        // ---- HTTP client (Guzzle) — SIN validación eager de credenciales --------
        $this->app->singleton(OAuth1Signer::class, function (): OAuth1Signer {
            return new OAuth1Signer(
                consumerKey:    (string) config('viafirma.client_id'),
                consumerSecret: (string) config('viafirma.client_secret'),
            );
        });

        $this->app->singleton(ProfileResponseParser::class);

        $this->app->singleton(ClientInterface::class . '@viafirma', function (): ClientInterface {
            return new GuzzleClient([
                'timeout'         => (int) config('viafirma.timeout', 30),
                'connect_timeout' => (int) config('viafirma.connect_timeout', 10),
            ]);
        });

        $this->app->singleton(GuzzleViafirmaClient::class, function ($app): GuzzleViafirmaClient {
            return new GuzzleViafirmaClient(
                http:    $app->make(ClientInterface::class . '@viafirma'),
                signer:  $app->make(OAuth1Signer::class),
                parser:  $app->make(ProfileResponseParser::class),
                baseUrl: (string) config('viafirma.base_url'),
                logger:  $app->make(SafePemLogger::class),
                timeout: (int) config('viafirma.timeout', 30),
            );
        });

        $this->app->bind(ViafirmaClient::class, function ($app) {
            if (config('viafirma.sandbox_mode', false)) {
                return $app->make(\App\Modules\Viafirma\Infrastructure\Http\MockViafirmaClient::class);
            }
            return $app->make(GuzzleViafirmaClient::class);
        });

        // ---- Repository ---------------------------------------------------------
        $this->app->bind(
            ViafirmaCertificateRequestRepositoryContract::class,
            ViafirmaCertificateRequestRepository::class
        );

        // ---- CSR Builders: inyectar opensslConf para Windows/WAMP64 -----------
        // Los builders heredan AbstractOpenSslCsrBuilder(opensslConf=null por defecto).
        // Sin opensslConf explícito, openssl_csr_new falla en Windows si OPENSSL_CONF
        // no está en el entorno del sistema. Leemos el .cnf empaquetado con la app.
        foreach ([
            \App\Modules\Viafirma\Infrastructure\Crypto\FePjCsrBuilder::class,
            \App\Modules\Viafirma\Infrastructure\Crypto\FePnCsrBuilder::class,
        ] as $builderClass) {
            $this->app->when($builderClass)
                ->needs('$opensslConf')
                ->give(fn () => config('viafirma.crypto.openssl_conf') ?: null);
        }

        // ---- UseCase como singleton EXPLÍCITO -----------------------------------
        // ⚠️  Windows / WAMP: singleton() sin closure usa reflection y provoca
        // stack overflow silencioso por la cadena profunda de 8 dependencias.
        // La factory closure las resuelve de forma plana (sin recursión profunda).
        $this->app->singleton(
            \App\Modules\Viafirma\Application\UseCases\IssueCertificateUseCase::class,
            function ($app): \App\Modules\Viafirma\Application\UseCases\IssueCertificateUseCase {
                return new \App\Modules\Viafirma\Application\UseCases\IssueCertificateUseCase(
                    crypto:             $app->make(\App\Modules\Viafirma\Domain\Contracts\CryptoServiceContract::class),
                    csrBuilderFactory:  $app->make(\App\Modules\Viafirma\Infrastructure\Crypto\CsrBuilderFactory::class),
                    keyVault:           $app->make(\App\Modules\Viafirma\Domain\Contracts\KeyVault::class),
                    client:             $app->make(\App\Modules\Viafirma\Domain\Contracts\ViafirmaClient::class),
                    repository:         $app->make(\App\Modules\Viafirma\Domain\Contracts\ViafirmaCertificateRequestRepositoryContract::class),
                    identityTypeMapper: $app->make(\App\Modules\Viafirma\Domain\Mappers\IdentityTypeMapper::class),
                    profileTypeMapper:  $app->make(\App\Modules\Viafirma\Domain\Mappers\ProfileTypeMapper::class),
                    logger:             $app->make(SafePemLogger::class),
                );
            }
        );

        // ---- Sprint 3: Polling + FSM + Resiliencia ----------------------------
        $this->app->singleton(\App\Modules\Viafirma\Application\Services\PollingScheduler::class);
        $this->app->singleton(\App\Modules\Viafirma\Infrastructure\CircuitBreaker\ViafirmaCircuitBreaker::class);
        $this->app->singleton(\App\Modules\Viafirma\Domain\StateMachine::class, function ($app) {
            return new \App\Modules\Viafirma\Domain\StateMachine(
                $app->make(SafePemLogger::class),
            );
        });
    }

    public function boot(): void
    {
        // Registrar comandos Artisan custom del módulo (sólo en CLI)
        if ($this->app->runningInConsole()) {
            $this->commands([
                \App\Console\Commands\Viafirma\ViafirmaMigrateCommand::class,
                \App\Console\Commands\Viafirma\ViafirmaMigrateStatusCommand::class,
                \App\Modules\Viafirma\Infrastructure\Console\ViafirmaHealthCheckCommand::class,
                \App\Console\Commands\Viafirma\DebugViafirmaIssueCommand::class,
            ]);
        }
    }
}
