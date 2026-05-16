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
use App\Modules\Viafirma\Infrastructure\Logging\SafePemLogger;
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
 * No registra migraciones a propósito (política §10.bis del roadmap): éstas
 * se ejecutan SIEMPRE individualmente vía `viafirma:migrate {file}`.
 */
final class ViafirmaServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // ---- Logger decorado ----------------------------------------------------
        $this->app->singleton(SafePemLogger::class, function ($app): SafePemLogger {
            $channel = config('viafirma.logging.channel');
            $base    = $channel ? Log::channel($channel) : Log::getFacadeRoot();
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
                    disk: Storage::disk((string) config('viafirma.crypto.vault_disk', 'local')),
                    crypt: $app->make(Encrypter::class),
                    vaultPath: (string) config('viafirma.crypto.vault_path', 'viafirma/vault'),
                ),
                // 'aws_kms' => $app->make(AwsKmsKeyVault::class),  // Sprint 5 (V-501)
                default => throw new \RuntimeException(
                    "Driver de KeyVault no soportado: '{$driver}'"
                ),
            };
        });

        // ---- HTTP client (Guzzle) ----------------------------------------------
        $this->app->singleton(OAuth1Signer::class, function (): OAuth1Signer {
            $key    = (string) config('viafirma.client_id');
            $secret = (string) config('viafirma.client_secret');
            if ($key === '' || $secret === '') {
                throw new \RuntimeException(
                    'VIAFIRMA_CLIENT_ID / VIAFIRMA_CLIENT_SECRET no están configurados.'
                );
            }
            return new OAuth1Signer($key, $secret);
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
        $this->app->bind(ViafirmaClient::class, GuzzleViafirmaClient::class);
    }

    public function boot(): void
    {
        // Registrar comandos Artisan custom del módulo (sólo en CLI)
        if ($this->app->runningInConsole()) {
            $this->commands([
                \App\Console\Commands\Viafirma\ViafirmaMigrateCommand::class,
                \App\Console\Commands\Viafirma\ViafirmaMigrateStatusCommand::class,
            ]);
        }
    }
}


