<?php

declare(strict_types=1);

namespace App\Modules\Viafirma\Domain\Enums;

/**
 * Perfiles soportados por Viafirma RA para Factura Electrónica DIAN.
 *
 * - FE_PJ → Persona Jurídica (10 atributos CSR · requiere organizationType · rues_check aplica)
 * - FE_PN → Persona Natural   ( 9 atributos CSR · sin organizationType    · sin rues_check)
 */
enum CertificateProfile: string
{
    case FE_PJ = 'FE-PJ';
    case FE_PN = 'FE-PN';

    public function requiresOrganizationType(): bool
    {
        return $this === self::FE_PJ;
    }

    public function appliesRuesCheck(): bool
    {
        return $this === self::FE_PJ;
    }

    public function csrAttributeCount(): int
    {
        return $this === self::FE_PJ ? 10 : 9;
    }

    public function label(): string
    {
        return match ($this) {
            self::FE_PJ => 'Persona Jurídica',
            self::FE_PN => 'Persona Natural',
        };
    }
}

