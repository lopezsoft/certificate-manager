<?php

declare(strict_types=1);

namespace App\Modules\Viafirma\Domain\Contracts;

use App\Modules\Viafirma\Application\DTOs\CsrInputDto;
use App\Modules\Viafirma\Application\DTOs\CsrResult;

/**
 * Strategy: cada perfil Viafirma (FE-PJ / FE-PN) tiene su propio set de
 * atributos en el Subject DN del CSR. La implementación firma el CSR con la
 * llave privada que recibe en PEM.
 */
interface CsrBuilderStrategy
{
    public function build(CsrInputDto $input, string $privateKeyPem): CsrResult;
}

