<?php

declare(strict_types=1);

namespace App\Modules\Viafirma\Domain\Contracts;

use App\Modules\Viafirma\Application\DTOs\ProfileDescriptor;
use App\Modules\Viafirma\Application\DTOs\SubmitCsrInputDto;
use App\Modules\Viafirma\Application\DTOs\SubmitCsrResultDto;

/**
 * Cliente del API Viafirma RA Colombia (PKCS#10).
 *
 * Patrón: Port (Hexagonal). Implementaciones: Guzzle / Saloon / Fake (tests).
 */
interface ViafirmaClient
{
    /**
     * GET /ra/available-profiles?codRa={ra}
     *
     * @return ProfileDescriptor[]
     *
     * @throws \App\Modules\Viafirma\Domain\Exceptions\ViafirmaClientException
     */
    public function getProfiles(string $raCode): array;

    /**
     * POST /request/fromCSR
     *
     * @throws \App\Modules\Viafirma\Domain\Exceptions\ViafirmaClientException
     */
    public function submitCsr(SubmitCsrInputDto $input): SubmitCsrResultDto;

    /**
     * GET /request/{codRequest}/publicId
     *
     * @throws \App\Modules\Viafirma\Domain\Exceptions\ViafirmaClientException
     */
    public function getPublicId(string $codRequest): string;

    /**
     * GET /request/{codRequest}/status
     *
     * @throws \App\Modules\Viafirma\Domain\Exceptions\ViafirmaClientException
     * @throws \App\Modules\Viafirma\Domain\Exceptions\TransientHttpException
     */
    public function getStatus(string $codRequest): \App\Modules\Viafirma\Application\DTOs\StatusResultDto;

    /**
     * Descarga el certificado P7B emitido.
     *
     * Usa la download_url (distinta de la API base) para descargar
     * el bundle de certificados en formato DER/P7B.
     *
     * @return string Contenido binario del .p7b (DER).
     *
     * @throws \App\Modules\Viafirma\Domain\Exceptions\ViafirmaClientException
     * @throws \App\Modules\Viafirma\Domain\Exceptions\TransientHttpException
     */
    public function downloadP7b(string $codRequest): string;
}

