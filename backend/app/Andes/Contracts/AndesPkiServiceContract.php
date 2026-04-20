<?php

namespace App\Andes\Contracts;

use App\Andes\DTOs\CertificateEmissionRequest;
use App\Andes\DTOs\CertificateEmissionResponse;
use App\Andes\DTOs\CertificateQueryResponse;

interface AndesPkiServiceContract
{
    public function requestElectronicInvoiceCertificate(CertificateEmissionRequest $dto): CertificateEmissionResponse;
    public function queryRequestStatus(string $solicitudId, string $documento): CertificateQueryResponse;
    public function getCertificatesByPerson(string $tipoDoc, string $documento): array;
    public function getCertificatePem(string $solicitudId, string $documento): string;
    public function revokeCertificate(string $serial, string $documento, int $causal, string $motivo): bool;
}

