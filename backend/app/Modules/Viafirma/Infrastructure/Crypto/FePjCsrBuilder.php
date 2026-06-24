<?php

declare(strict_types=1);

namespace App\Modules\Viafirma\Infrastructure\Crypto;

use App\Modules\Viafirma\Application\DTOs\CsrInputDto;
use App\Modules\Viafirma\Domain\Enums\CertificateProfile;
use App\Modules\Viafirma\Domain\Exceptions\CsrBuildException;

/**
 * Builder de CSR para perfil FE-PJ (Persona Jurídica) — 11 atributos.
 *
 *  CN, C, ST, L, STREET, O, OU, SERIALNUMBER (NIT), E, GN, SN
 */
final class FePjCsrBuilder extends AbstractOpenSslCsrBuilder
{
    protected function expectedAttributeCount(): int
    {
        return 11;
    }

    protected function validate(CsrInputDto $input): void
    {
        if ($input->profile !== CertificateProfile::FE_PJ) {
            throw new CsrBuildException(
                'FePjCsrBuilder sólo acepta perfil FE_PJ; recibido: ' . $input->profile->value
            );
        }

        $this->assertNotBlank($input->country, 'C');
        $this->assertNotBlank($input->state, 'ST');
        $this->assertNotBlank($input->locality, 'L');
        $this->assertNotBlank($input->street, 'STREET');
        $this->assertNotBlank($input->organization, 'O');
        $this->assertNotBlank($input->organizationUnit, 'OU');
        $this->assertNotBlank($input->serialNumber, 'SERIALNUMBER (NIT)');
        $this->assertNotBlank($input->email, 'E');
        $this->assertNotBlank($input->givenName, 'GN');
        $this->assertNotBlank($input->surname, 'SN');

        // Validación de formato country delegada al AbstractOpenSslCsrBuilder::assertValidCountryAlpha2()
        // que se ejecuta en build() antes de llegar aquí.
    }

    protected function dn(CsrInputDto $input): array
    {
        // CN = {legalNameCorp} - {departament} según dnPattern Viafirma
        $cn = trim($input->organization . ' - ' . ($input->state ?? ''));

        return [
            'CN'           => $cn,
            'C'            => $input->country,
            'ST'           => $input->state,
            'L'            => $input->locality,
            'street'       => $input->street,
            'O'            => $input->organization,
            'OU'           => $input->organizationUnit,
            'serialNumber' => $input->serialNumber,
            'emailAddress' => $input->email,
            'GN'           => $input->givenName,
            'SN'           => $input->surname,
        ];
    }
}

