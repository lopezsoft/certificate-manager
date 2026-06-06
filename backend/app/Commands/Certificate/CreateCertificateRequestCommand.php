<?php

namespace App\Commands\Certificate;

use Illuminate\Http\UploadedFile;

/**
 * Comando para crear una nueva solicitud de certificado.
 */
final class CreateCertificateRequestCommand implements CertificateCommandInterface
{
    /**
     * @param UploadedFile[] $files
     */
    public function __construct(
        public readonly int     $companyId,
        public readonly int     $cityId,
        public readonly int     $identityDocumentId,
        public readonly int     $typeOrganizationId,
        public readonly int     $entityDocumentTypeId,
        public readonly string  $documentNumber,
        public readonly string  $address,
        public readonly string  $legalRepresentative,
        public readonly ?string $legalRepEmail,
        public readonly string  $companyName,
        public readonly string  $dni,
        public readonly int     $life,
        public readonly ?string $info,
        public readonly array   $files,
        public readonly int     $userId,
    ) {}
}

