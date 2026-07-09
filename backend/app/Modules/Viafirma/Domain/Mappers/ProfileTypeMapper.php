<?php

declare(strict_types=1);

namespace App\Modules\Viafirma\Domain\Mappers;

use App\Models\TypeOrganization;
use App\Modules\Viafirma\Domain\Enums\CertificateProfile;
use App\Modules\Viafirma\Domain\Exceptions\UnsupportedOrganizationTypeException;

/**
 * Traduce el catálogo productivo {@see TypeOrganization} al enum
 * {@see CertificateProfile} (FE_PJ / FE_PN) que decide el flujo Viafirma.
 *
 * Mapeo (códigos productivos confirmados):
 *   code = 1 → Persona Jurídica → FE_PJ
 *   code = 2 → Persona Natural  → FE_PN
 *
 * Patrón: Anti-Corruption Layer.
 */
final class ProfileTypeMapper
{
    public function fromTypeOrganization(TypeOrganization $organization): CertificateProfile
    {
        $code = (int) ($organization->code ?? throw new UnsupportedOrganizationTypeException(
            "TypeOrganization id={$organization->id} no tiene código definido."
        ));

        return match ($code) {
            1 => CertificateProfile::FE_PJ,
            2 => CertificateProfile::FE_PN,
            default => throw new UnsupportedOrganizationTypeException(
                "TypeOrganization id={$organization->id} (code={$code}) no mapea a un perfil Viafirma soportado."
            ),
        };
    }
}

