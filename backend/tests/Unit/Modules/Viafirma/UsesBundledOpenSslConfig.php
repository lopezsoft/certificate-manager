<?php

declare(strict_types=1);

namespace Tests\Unit\Modules\Viafirma;

/**
 * Helper para tests del módulo Viafirma — resuelve la ruta del openssl.cnf
 * bundled (necesario en entornos sin OPENSSL_CONF, como WAMP/Windows).
 */
trait UsesBundledOpenSslConfig
{
    protected static function bundledOpensslConf(): string
    {
        // tests/Unit/Modules/Viafirma  ↗ 5 niveles arriba = backend/
        return realpath(__DIR__ . '/../../../../') . DIRECTORY_SEPARATOR
             . 'config' . DIRECTORY_SEPARATOR
             . 'viafirma' . DIRECTORY_SEPARATOR
             . 'openssl.cnf';
    }
}

