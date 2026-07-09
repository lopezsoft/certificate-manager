<?php

namespace App\Commands\Certificate;

/**
 * Comando para crear una nueva solicitud de certificado.
 *
 * Los adjuntos se reciben como array de objetos con estructura:
 * [
 *   {
 *     "base64": "...",      // Requerido
 *     "name": "...",        // Opcional (se genera si no existe)
 *     "type": "...",        // Opcional (se detecta si no existe)
 *     "size": 12345         // Opcional (se calcula si no existe)
 *   }
 * ]
 */
final class CreateCertificateRequestCommand implements CertificateCommandInterface
{
    /**
     * @param array $attachments Array de adjuntos con estructura {base64, name?, type?, size?}
     */
    public function __construct(
        public readonly int     $companyId,
        public readonly int     $countryId,
        public readonly int     $cityId,
        public readonly int     $identityDocumentId,
        public readonly int     $typeOrganizationId,
        public readonly ?int    $entityDocumentTypeId,
        public readonly string  $documentNumber,
        public readonly string  $address,
        public readonly ?string $legalRepresentative,
        public readonly ?string $legalRepFirstName,
        public readonly ?string $legalRepLastName,
        public readonly ?string $legalRepEmail,
        public readonly string  $companyName,
        public readonly string  $dni,
        public readonly int     $life,
        public readonly ?string $info,
        public readonly ?string $mobile,
        public readonly ?string $phone,
        public readonly array   $attachments,
        public readonly int     $userId,
    ) {}
}

