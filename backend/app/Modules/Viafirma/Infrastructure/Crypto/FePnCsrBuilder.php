<?php

declare(strict_types=1);

namespace App\Modules\Viafirma\Infrastructure\Crypto;

use App\Modules\Viafirma\Application\DTOs\CsrInputDto;
use App\Modules\Viafirma\Domain\Enums\CertificateProfile;
use App\Modules\Viafirma\Domain\Exceptions\CsrBuildException;

/**
 * Builder de CSR para perfil FE-PN (Persona Natural) — 9 atributos.
 *
 *  C, ST, L, STREET, SERIALNUMBER (cédula), E, GN, SN
 *
 *  Nótese: NO incluye O ni OU.
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

        $this->assertNotBlank($input->country, 'C');
        $this->assertNotBlank($input->state, 'ST');
        $this->assertNotBlank($input->locality, 'L');
        $this->assertNotBlank($input->street, 'STREET');
        $this->assertNotBlank($input->serialNumber, 'SERIALNUMBER (cédula)');
        $this->assertNotBlank($input->email, 'E');
        $this->assertNotBlank($input->givenName, 'GN');
        $this->assertNotBlank($input->surname, 'SN');

        if ($input->organization !== null && trim($input->organization) !== '') {
            throw new CsrBuildException("El perfil FE-PN no admite el atributo 'O' (organización).");
        }
        if ($input->organizationUnit !== null && trim($input->organizationUnit) !== '') {
            throw new CsrBuildException("El perfil FE-PN no admite el atributo 'OU' (unidad organizativa).");
        }

        if (strlen($input->country) !== 2) {
            throw new CsrBuildException("El country code 'C' debe ser ISO 3166-1 alpha-2 (2 letras).");
        }
    }

    protected function dn(CsrInputDto $input): array
    {
        // Notar: aquí se devuelve el "DN visible" — 9 atributos contables.
        // El campo `STREET` se mantiene como atributo separado.
        return [
            'C'            => $input->country,
            'ST'           => $input->state,
            'L'            => $input->locality,
            'street'       => $input->street,
            'serialNumber' => $input->serialNumber,
            'emailAddress' => $input->email,
            'GN'           => $input->givenName,
            'SN'           => $input->surname,
            // 9º atributo: CN derivado del nombre completo (Viafirma lo exige aunque
            // el doc V1.1 lo mencione implícitamente vía dnPattern; queda parametrizable
            // por validate() del dnPattern en Sprint 2 — V-211).
            'CN'           => trim($input->givenName . ' ' . $input->surname),
        ];
    }
}

