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
     * A partir de la API v3.4.53, la respuesta 200 OK devuelve directamente
     * `codRequest` y `publicId` (ambos obligatorios).
     *
     * @throws \App\Modules\Viafirma\Domain\Exceptions\ViafirmaClientException
     */
    public function submitCsr(SubmitCsrInputDto $input): SubmitCsrResultDto;

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
     * API v3.4.53: usa `downloadCertificateServlet?req={publicId}` (URL de descarga).
     * Reemplaza el antiguo `/request/{codRequest}/download/pkcs7`.
     *
     * @param string $publicId Identificador público devuelto por `submitCsr()`.
     * @return string Contenido binario del .p7b (DER).
     *
     * @throws \App\Modules\Viafirma\Domain\Exceptions\ViafirmaClientException
     * @throws \App\Modules\Viafirma\Domain\Exceptions\TransientHttpException
     */
    public function downloadP7b(string $publicId): string;

    /**
     * POST /request/revoke/code/{revokingCode}
     *
     * Revoca un certificado ya emitido utilizando el código de revocación
     * que Viafirma envió al usuario final por correo tras la emisión.
     *
     * Body: { "revocationReason": <int> }
     * Respuesta 200: { "code": "new-revocation-requestCode" }
     *
     * @param string $revokingCode    Código de revocación recibido por el usuario.
     * @param int    $revocationReason Código del motivo (0,1,2,3,4,5,9,10).
     * @return string El nuevo revocation requestCode devuelto por Viafirma.
     *
     * @throws \App\Modules\Viafirma\Domain\Exceptions\ViafirmaClientException
     */
    public function revokeCertificate(string $revokingCode, int $revocationReason): string;

    /**
     * GET /services/accreditation/{codRequest}
     *
     * Obtiene el enlace del portal KYC para que el usuario inicie el proceso
     * de verificación de identidad (onboarding).
     * Solo disponible cuando remote_status === 'accreditation'.
     *
     * Respuesta 200: { "link": "https://..." }
     *
     * @throws \App\Modules\Viafirma\Domain\Exceptions\ViafirmaClientException
     */
    public function getAccreditationLink(string $codRequest): string;
}

