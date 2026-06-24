<?php

declare(strict_types=1);

namespace App\Modules\Viafirma\Infrastructure\Crypto;

use App\Modules\Viafirma\Application\DTOs\CsrInputDto;
use App\Modules\Viafirma\Domain\Enums\CertificateProfile;
use App\Modules\Viafirma\Domain\Exceptions\CsrBuildException;

/**
 * Builder de CSR para perfil FE-PN (Persona Natural) — 9 atributos.
 *
 *  C, ST, L, STREET, SERIALNUMBER (cédula), E, GN, SN, CN
 *
 *  Según documentación oficial Viafirma §3.2:
 *  País (C), Departamento (ST), Ciudad (L), Dirección (STREET),
 *  NIT/Cédula (SERIALNUMBER), Email (E), Nombre (GN), Apellidos (SN), CN.
 */
final class FePnCsrBuilder extends AbstractOpenSslCsrBuilder
{
    protected function expectedAttributeCount(): int
    {
        return 9;
    }

    protected function validate(CsrInputDto $input): void
    {
        if ($input->profile !== CertificateProfile::FE_PN) {
            throw new CsrBuildException(
                'FePnCsrBuilder sólo acepta perfil FE_PN; recibido: ' . $input->profile->value
            );
        }

        // ── Campos obligatorios según §3.2 ──────────────────────────────────
        $this->assertNotBlank($input->country,      'C');
        $this->assertNotBlank($input->state,        'ST (departamento)');
        $this->assertNotBlank($input->locality,     'L (ciudad)');
        $this->assertNotBlank($input->street,       'STREET');
        $this->assertNotBlank($input->serialNumber, 'SERIALNUMBER (cédula)');
        $this->assertNotBlank($input->email,        'E');
        $this->assertNotBlank($input->givenName,    'GN');
        $this->assertNotBlank($input->surname,      'SN');

        // Validación de formato country delegada al AbstractOpenSslCsrBuilder::assertValidCountryAlpha2()
        // que se ejecuta en build() antes de llegar aquí.
    }


    protected function dn(CsrInputDto $input): array
    {
        // Documentación oficial Viafirma §3.2: 9 atributos FE-PN
        return [
            'C'            => $input->country,
            'ST'           => $input->state,
            'L'            => $input->locality,
            'street'       => $input->street,
            'serialNumber' => $input->serialNumber,
            'emailAddress' => $input->email,
            'GN'           => $input->givenName,
            'SN'           => $input->surname,
            'CN'           => trim($input->givenName . ' ' . $input->surname),
        ];
    }
}

