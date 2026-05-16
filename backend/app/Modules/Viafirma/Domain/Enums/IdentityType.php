<?php

declare(strict_types=1);

namespace App\Modules\Viafirma\Domain\Enums;

/**
 * Tipo de documento del SOLICITANTE (KYC) según Viafirma V1.1.
 *
 * - IDC → Identity Card (Cédula de Ciudadanía / Cédula de Extranjería)
 * - PAS → Pasaporte
 *
 * Nota: el NIT (DIAN code '31') identifica empresas, no solicitantes, por lo que
 * jamás se envía como identityType.
 */
enum IdentityType: string
{
    case IDC = 'IDC';
    case PAS = 'PAS';
}

