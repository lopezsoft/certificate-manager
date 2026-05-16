<?php

declare(strict_types=1);

namespace App\Modules\Viafirma\Domain\Mappers;

use App\Models\IdentityDocument;
use App\Modules\Viafirma\Domain\Enums\IdentityType;
use App\Modules\Viafirma\Domain\Exceptions\UnsupportedIdentityDocumentException;

/**
 * Traduce el catálogo productivo {@see IdentityDocument} (DIAN codes) al enum
 * {@see IdentityType} aceptado por Viafirma RA.
 *
 * Mapeo (códigos DIAN):
 *   '13' (CC, Cédula de Ciudadanía)  → IDC
 *   '22' (CE, Cédula de Extranjería) → IDC
 *   '41' (PAS, Pasaporte — seeder opcional §3.0.4) → PAS
 *   '31' (NIT) → ❌ no es solicitante, lanza excepción.
 *
 * Patrón: Anti-Corruption Layer.
 */
final class IdentityTypeMapper
{
    private const IDC_CODES = ['13', '22'];
    private const PAS_CODES = ['41'];

    /** Mapa: abbreviation → IdentityType (fallback robusto si no hay code DIAN). */
    private const ABBREVIATION_MAP = [
        'CC'  => IdentityType::IDC,
        'CE'  => IdentityType::IDC,
        'PAS' => IdentityType::PAS,
    ];

    public function fromIdentityDocument(IdentityDocument $document): IdentityType
    {
        $code = (string) ($document->code ?? '');
        $abbreviation = strtoupper((string) ($document->abbreviation ?? ''));

        if (in_array($code, self::IDC_CODES, true)) {
            return IdentityType::IDC;
        }

        if (in_array($code, self::PAS_CODES, true)) {
            return IdentityType::PAS;
        }

        if (isset(self::ABBREVIATION_MAP[$abbreviation])) {
            return self::ABBREVIATION_MAP[$abbreviation];
        }

        throw new UnsupportedIdentityDocumentException(
            "IdentityDocument id={$document->id} (code='{$code}', abbreviation='{$abbreviation}') "
            . 'no es válido como identityType de Viafirma (sólo CC/CE → IDC y Pasaporte → PAS son aceptados).'
        );
    }
}

