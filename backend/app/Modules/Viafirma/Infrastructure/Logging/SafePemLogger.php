<?php

declare(strict_types=1);

namespace App\Modules\Viafirma\Infrastructure\Logging;

use Psr\Log\LoggerInterface;
use Stringable;

/**
 * Decorator (PSR-3) que redacta material sensible antes de delegar al logger real.
 *
 * Patrón: Decorator + Chain of Responsibility (lista de redactores).
 *
 * Política de seguridad:
 *  - NUNCA se permite que un PEM (privado o público), un .p12 base64, un PIN
 *    o un token OAuth aparezca en logs.
 *  - Si se detecta, se reemplaza por un placeholder con su SHA-256 truncado
 *    (para correlación sin exponer el material).
 *
 *  Se registra como `SafePemLogger::class` en el container y se entrega vía
 *  inyección a todos los servicios del módulo Viafirma (V-110).
 */
final class SafePemLogger implements LoggerInterface
{
    /** @var array<array{pattern:string,label:string}> */
    private const REDACTORS = [
        // RSA / EC private keys (PKCS#1 y PKCS#8)
        ['pattern' => '/-----BEGIN (?:RSA |EC |DSA |ENCRYPTED |)PRIVATE KEY-----[\s\S]+?-----END (?:RSA |EC |DSA |ENCRYPTED |)PRIVATE KEY-----/', 'label' => 'PRIVATE_KEY'],
        // Public keys
        ['pattern' => '/-----BEGIN PUBLIC KEY-----[\s\S]+?-----END PUBLIC KEY-----/', 'label' => 'PUBLIC_KEY'],
        // CSR
        ['pattern' => '/-----BEGIN CERTIFICATE REQUEST-----[\s\S]+?-----END CERTIFICATE REQUEST-----/', 'label' => 'CSR'],
        // PKCS#7 / certificate
        ['pattern' => '/-----BEGIN PKCS7-----[\s\S]+?-----END PKCS7-----/', 'label' => 'PKCS7'],
        ['pattern' => '/-----BEGIN CERTIFICATE-----[\s\S]+?-----END CERTIFICATE-----/', 'label' => 'CERTIFICATE'],
    ];

    /** Claves de contexto que se redactan completamente (case-insensitive). */
    private const SENSITIVE_CONTEXT_KEYS = [
        'private_key', 'privatekey', 'private_key_pem',
        'p12', 'p12_password', 'pin', 'password',
        'oauth_signature', 'oauth_token', 'access_token', 'authorization',
        'client_secret', 'viafirma_client_secret',
    ];

    public function __construct(private readonly LoggerInterface $inner) {}

    public function emergency(string|Stringable $message, array $context = []): void
    {
        $this->inner->emergency($this->redact($message), $this->redactContext($context));
    }
    public function alert(string|Stringable $message, array $context = []): void
    {
        $this->inner->alert($this->redact($message), $this->redactContext($context));
    }
    public function critical(string|Stringable $message, array $context = []): void
    {
        $this->inner->critical($this->redact($message), $this->redactContext($context));
    }
    public function error(string|Stringable $message, array $context = []): void
    {
        $this->inner->error($this->redact($message), $this->redactContext($context));
    }
    public function warning(string|Stringable $message, array $context = []): void
    {
        $this->inner->warning($this->redact($message), $this->redactContext($context));
    }
    public function notice(string|Stringable $message, array $context = []): void
    {
        $this->inner->notice($this->redact($message), $this->redactContext($context));
    }
    public function info(string|Stringable $message, array $context = []): void
    {
        $this->inner->info($this->redact($message), $this->redactContext($context));
    }
    public function debug(string|Stringable $message, array $context = []): void
    {
        $this->inner->debug($this->redact($message), $this->redactContext($context));
    }
    public function log($level, string|Stringable $message, array $context = []): void
    {
        $this->inner->log($level, $this->redact($message), $this->redactContext($context));
    }

    /** Redacta una cadena (mensaje). */
    public function redact(string|Stringable $message): string
    {
        $text = (string) $message;
        foreach (self::REDACTORS as $r) {
            $text = (string) preg_replace_callback($r['pattern'], function (array $m) use ($r): string {
                $hash = substr(hash('sha256', $m[0]), 0, 8);
                return "[REDACTED:{$r['label']} sha256_8={$hash}]";
            }, $text);
        }
        return $text;
    }

    /** Redacta un array de contexto recursivamente. */
    public function redactContext(array $context): array
    {
        $out = [];
        foreach ($context as $k => $v) {
            $keyLower = strtolower((string) $k);
            if (in_array($keyLower, self::SENSITIVE_CONTEXT_KEYS, true)) {
                $out[$k] = '[REDACTED]';
                continue;
            }
            $out[$k] = match (true) {
                is_string($v)            => $this->redact($v),
                $v instanceof Stringable => $this->redact($v),
                is_array($v)             => $this->redactContext($v),
                default                  => $v,
            };
        }
        return $out;
    }
}

