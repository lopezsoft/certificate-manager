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

        // ── Validación de formato ────────────────────────────────────────────
        // AJUSTE 1: El country code DEBE ser ISO 3166-1 alpha-2 (2 letras).
        // No se silencia ni se sobreescribe el valor — el dato de origen es responsable.
        if (strlen(trim($input->country)) !== 2) {
            throw new CsrBuildException(
                "El campo 'C' debe ser un código ISO 3166-1 alpha-2 de exactamente 2 letras; recibido: '{$input->country}'."
            );
        }

        // AJUSTE 2: O (organización) es ignorado silenciosamente si viene informado.
        // FE-PN no lleva O en el DN; no se lanza excepción para no bloquear flujos
        // que reutilizan el mismo DTO en ambos perfiles.
        // (No hay acción: simplemente no se incluye en dn())

        // AJUSTE 3: OU (unidad organizativa) — mismo criterio que O.
        // Ignorado silenciosamente; el dn() lo omite por diseño.
        // (No hay acción: simplemente no se incluye en dn())
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

