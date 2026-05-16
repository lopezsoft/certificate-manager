<?php

declare(strict_types=1);

namespace App\Modules\Viafirma\Infrastructure\Crypto;

use App\Modules\Viafirma\Domain\Contracts\CsrBuilderStrategy;
use App\Modules\Viafirma\Domain\Enums\CertificateProfile;
use Illuminate\Contracts\Container\Container;

/**
 * Factory que resuelve el builder correcto según el perfil.
 *
 * Patrón: Strategy + Factory Method.
 */
final class CsrBuilderFactory
{
    public function __construct(private readonly Container $container) {}

    public function for(CertificateProfile $profile): CsrBuilderStrategy
    {
        return match ($profile) {
            CertificateProfile::FE_PJ => $this->container->make(FePjCsrBuilder::class),
            CertificateProfile::FE_PN => $this->container->make(FePnCsrBuilder::class),
        };
    }
}

