<?php

declare(strict_types=1);

namespace App\Modules\Viafirma\Infrastructure\Crypto;

use App\Modules\Viafirma\Application\DTOs\CsrInputDto;
use App\Modules\Viafirma\Domain\Enums\CertificateProfile;
use App\Modules\Viafirma\Domain\Exceptions\CsrBuildException;

/**
 * Builder de CSR para perfil FE-PN (Persona Natural) — 7 atributos.
 *
 *  C, STREET, SERIALNUMBER (cédula), E, GN, SN, CN
 *
 *  Nótese: NO incluye O, OU, L ni ST (API v3.4.53).
 */
final class FePnCsrBuilder extends AbstractOpenSslCsrBuilder
{
    protected function expectedAttributeCount(): int
    {
        return 7;
    }

    protected function validate(CsrInputDto $input): void
    {
        if ($input->profile !== CertificateProfile::FE_PN) {
            throw new CsrBuildException(
                'FePnCsrBuilder sólo acepta perfil FE_PN; recibido: ' . $input->profile->value
            );
        }

        $this->assertNotBlank($input->country, 'C');
        $this->assertNotBlank($input->street, 'STREET');
        $this->assertNotBlank($input->serialNumber, 'SERIALNUMBER (cédula)');
        $this->assertNotBlank($input->email, 'E');
        $this->assertNotBlank($input->givenName, 'GN');
        $this->assertNotBlank($input->surname, 'SN');

        // API v3.4.53: FE-PN no admite O, OU, L ni ST en el CSR.
        if ($input->organization !== null && trim($input->organization) !== '') {
            throw new CsrBuildException("El perfil FE-PN no admite el atributo 'O' (organización).");
        }
        if ($input->organizationUnit !== null && trim($input->organizationUnit) !== '') {
            throw new CsrBuildException("El perfil FE-PN no admite el atributo 'OU' (unidad organizativa).");
        }
        if ($input->locality !== null && trim($input->locality) !== '') {
            throw new CsrBuildException("El perfil FE-PN no admite el atributo 'L' (localidad) — API v3.4.53.");
        }
        if ($input->state !== null && trim($input->state) !== '') {
            throw new CsrBuildException("El perfil FE-PN no admite el atributo 'ST' (departamento) — API v3.4.53.");
        }

        if (strlen($input->country) !== 2) {
            throw new CsrBuildException("El country code 'C' debe ser ISO 3166-1 alpha-2 (2 letras).");
        }
    }

    protected function dn(CsrInputDto $input): array
    {
        // API v3.4.53: 7 atributos (sin ST, L, O, OU)
        return [
            'C'            => $input->country,
            'street'       => $input->street,
            'serialNumber' => $input->serialNumber,
            'emailAddress' => $input->email,
            'GN'           => $input->givenName,
            'SN'           => $input->surname,
            'CN'           => trim($input->givenName . ' ' . $input->surname),
        ];
    }
}

