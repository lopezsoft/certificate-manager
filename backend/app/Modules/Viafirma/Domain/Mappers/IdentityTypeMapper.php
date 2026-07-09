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
 *   '21' (TE, Tarjeta de extranjería) → IDC
 *   '22' (CE, Cédula de Extranjería) → IDC
 *   '31' (NIT, Número de Identificación Tributaria) → IDC
 *   '41' (PAS, Pasaporte — seeder opcional §3.0.4) → PAS
 *   '42' (DE, Documento de identificación extranjero) → IDC
 *   '47' (PEP, PEP (Permiso Especial de Permanencia)) → IDC
 *   '48' (PPT, Permiso Protección Temporal) → IDC
 *   '50' (NOP, NIT de otro país) → IDC
 *
 * Patrón: Anti-Corruption Layer.
 */
final class IdentityTypeMapper
{
    private const IDC_CODES = ['13', '21', '22', '31', '42', '47', '48', '50'];
    private const PAS_CODES = ['41'];

    /** Mapa: abbreviation → IdentityType (fallback robusto si no hay code DIAN). */
    private const ABBREVIATION_MAP = [
        'CC'  => IdentityType::IDC,
        'CE'  => IdentityType::IDC,
        'TE'  => IdentityType::IDC,
        'NIT' => IdentityType::IDC,
        'DE'  => IdentityType::IDC,
        'PEP' => IdentityType::IDC,
        'PPT' => IdentityType::IDC,
        'NOP' => IdentityType::IDC,
        'PAS' => IdentityType::PAS,
    ];

    public function fromIdentityDocument(IdentityDocument $document): IdentityType
    {
        if (empty($document->code)) {
            throw new UnsupportedIdentityDocumentException(
                "IdentityDocument id={$document->id} sin code (DIAN). Este campo es obligatorio."
            );
        }

        if (empty($document->abbreviation)) {
            throw new UnsupportedIdentityDocumentException(
                "IdentityDocument id={$document->id} sin abbreviation. Este campo es obligatorio."
            );
        }

        $code = (string) $document->code;
        $abbreviation = strtoupper((string) $document->abbreviation);

        // Primero intentar mapeo por código DIAN (más fiable)
        if (in_array($code, self::IDC_CODES, true)) {
            return IdentityType::IDC;
        }

        if (in_array($code, self::PAS_CODES, true)) {
            return IdentityType::PAS;
        }

        // Fallback: mapeo por abbreviation (si el código DIAN no está en nuestro catálogo)
        if (isset(self::ABBREVIATION_MAP[$abbreviation])) {
            return self::ABBREVIATION_MAP[$abbreviation];
        }

        // Si llegamos aquí, el documento no es soportado por Viafirma
        throw new UnsupportedIdentityDocumentException(
            "IdentityDocument id={$document->id} (code='{$code}', abbreviation='{$abbreviation}') "
            . 'no es soportado como identityType de Viafirma RA. Valores válidos: IDC (cédula) o PAS (pasaporte).'
        );
    }
}

