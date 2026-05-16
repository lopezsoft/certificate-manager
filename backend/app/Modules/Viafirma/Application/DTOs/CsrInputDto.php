<?php

declare(strict_types=1);

namespace App\Modules\Viafirma\Application\DTOs;

use App\Modules\Viafirma\Domain\Enums\CertificateProfile;
use App\Modules\Viafirma\Domain\Enums\OrganizationType;

/**
 * Datos necesarios para construir un CSR PKCS#10 para Viafirma.
 *
 * Convención de campos (alineada con §2.bis.5 del roadmap):
 *  - country         (C)            ISO 3166-1 alpha-2  — ej. "CO"
 *  - state           (ST)           Departamento        — ej. "ANTIOQUIA"
 *  - locality        (L)            Ciudad              — ej. "MEDELLÍN"
 *  - street          (STREET)       Dirección
 *  - organization    (O)            Razón social        — sólo FE_PJ
 *  - organizationUnit(OU)           Unidad organizativa — sólo FE_PJ (opcional)
 *  - serialNumber    (SERIALNUMBER) NIT (PJ) o cédula (PN)
 *  - email           (E)            Email a embeber en el certificado
 *  - givenName       (GN)           Nombre(s) del titular o del Representante Legal (PJ)
 *  - surname         (SN)           Apellidos del titular o del Representante Legal (PJ)
 *
 *  - organizationType  enum Viafirma — sólo FE_PJ
 *  - emailCertificate  email del payload (puede diferir del E del CSR)
 *  - identity          cédula/pasaporte del solicitante (RL en PJ, titular en PN)
 */
final class CsrInputDto
{
    public function __construct(
        public readonly CertificateProfile $profile,
        public readonly string $country,
        public readonly string $state,
        public readonly string $locality,
        public readonly string $street,
        public readonly string $serialNumber,
        public readonly string $email,
        public readonly string $givenName,
        public readonly string $surname,
        public readonly ?string $organization = null,
        public readonly ?string $organizationUnit = null,
        public readonly ?OrganizationType $organizationType = null,
        public readonly ?string $emailCertificate = null,
        public readonly ?string $identity = null,
    ) {}
}

