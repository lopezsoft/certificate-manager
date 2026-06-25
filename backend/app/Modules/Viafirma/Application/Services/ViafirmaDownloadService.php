<?php

declare(strict_types=1);

namespace App\Modules\Viafirma\Application\Services;

use App\Common\HttpResponseMessages;
use App\Modules\Viafirma\Domain\Contracts\KeyVault;
use App\Modules\Viafirma\Domain\Contracts\ViafirmaCertificateRequestRepositoryContract;
use App\Modules\Viafirma\Infrastructure\Logging\SafePemLogger;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Servicio que encapsula la lógica de descarga del P12 ensamblado comprimido.
 *
 * Extraído del antiguo `ViafirmaCertificateController` para que la nueva
 * capa HTTP unificada (`CertificateIssuanceController`) pueda invocarla
 * sin acoplarse al módulo Viafirma directamente.
 */
final class ViafirmaDownloadService
{
    public function __construct(
        private readonly ViafirmaCertificateRequestRepositoryContract $repository,
        private readonly KeyVault $vault,
        private readonly SafePemLogger $logger,
        private readonly \App\Services\Certificates\CertificateStoragePathResolver $pathResolver,
    ) {}

    /**
     * Devuelve metadata + URL temporal firmada (24h) + PIN del P12.
     *
     * @param string $uuid  UUID de la solicitud (`certificate_requests.uuid`)
     */
    public function metadataFor(string $uuid, ?int $userId = null): JsonResponse
    {
        $certificateRequest = \App\Models\CertificateRequest::where('uuid', $uuid)->first();

        if ($certificateRequest === null) {
            return HttpResponseMessages::getResponse404([
                'message' => 'Solicitud de certificado no encontrada.',
            ]);
        }

        $entityParent = $this->repository->findByCertificateRequestId($certificateRequest->id);

        if ($entityParent === null) {
            return HttpResponseMessages::getResponse404([
                'message' => 'No existe un trámite Viafirma para esta solicitud.',
            ]);
        }

        $entity = $entityParent->state;

        // Validación centralizada: solo se puede descargar si el certificado está en estado PROCESSED
        if (!$certificateRequest->canDownloadCertificate()) {
            return HttpResponseMessages::getResponse409([
                'message' => "El certificado no está disponible para descarga. Estado actual: {$certificateRequest->request_status}.",
            ]);
        }

        if (empty($entity->p12_storage_path)) {
            return HttpResponseMessages::getResponse409([
                'message' => 'El archivo P12 no se ha generado aún.',
            ]);
        }

        if (empty($entity->p12_password_ref) || $entity->p12_password_ref === 'PURGED') {
            return HttpResponseMessages::getResponse410([
                'message' => 'El PIN del certificado ha sido purgado. Contacte soporte.',
            ]);
        }

        $pin  = $this->vault->retrieve($entity->p12_password_ref);
        $disk = $this->pathResolver->disk();

        $temporaryUrl = null;
        try {
            $temporaryUrl = Storage::disk($disk)->temporaryUrl(
                $entity->p12_storage_path,
                now()->addHours(24),
            );
        } catch (\RuntimeException) {
            $temporaryUrl = null; // disk local
        }

        $this->logger->info('viafirma.download.served', [
            'uuid'   => $uuid,
            'cr_id'  => $certificateRequest->id,
            'vf_id'  => $entity->id,
            'user'   => $userId,
        ]);

        return HttpResponseMessages::getResponse([
            'dataRecords' => [
                'data' => [
                    'p12_pin'      => $pin,
                    'p12_filename' => basename($entity->p12_storage_path),
                    'download_url' => $temporaryUrl,
                    'expires_at'   => now()->addHours(24)->toISOString(),
                ],
            ]
        ]);
    }


    /**
     * Devuelve el P12 codificado en Base64 + PIN, para uso desatendido por el cliente.
     * Funciona igual con cualquier disco (S3 o local).
     *
     * @param string $uuid  UUID de la solicitud (`certificate_requests.uuid`)
     */
    public function base64For(string $uuid, ?int $userId = null): JsonResponse
    {
        $certificateRequest = \App\Models\CertificateRequest::where('uuid', $uuid)->first();

        if ($certificateRequest === null) {
            return HttpResponseMessages::getResponse404([
                'message' => 'Solicitud de certificado no encontrada.',
            ]);
        }

        $entityParent = $this->repository->findByCertificateRequestId($certificateRequest->id);


        if ($entityParent === null) {
            return HttpResponseMessages::getResponse404([
                'message' => 'No existe un trámite Viafirma para esta solicitud.',
            ]);
        }

        $entity = $entityParent->state;

        // Validación centralizada: solo se puede descargar si el certificado está en estado PROCESSED
        if (!$certificateRequest->canDownloadCertificate()) {
            return HttpResponseMessages::getResponse409([
                'message' => "El certificado no está disponible para descarga. Estado actual: {$certificateRequest->request_status}.",
            ]);
        }

        if (empty($entity->p12_storage_path)) {
            return HttpResponseMessages::getResponse409([
                'message' => 'Archivo P12 no disponible.',
            ]);
        }

        if (empty($entity->p12_password_ref) || $entity->p12_password_ref === 'PURGED') {
            return HttpResponseMessages::getResponse410([
                'message' => 'El PIN del certificado ha sido purgado. Contacte soporte.',
            ]);
        }

        $disk   = $this->pathResolver->disk();
        $binary = Storage::disk($disk)->get($entity->p12_storage_path);

        if ($binary === null || $binary === '') {
            return HttpResponseMessages::getResponse410([
                'message' => 'No se pudo leer el archivo P12 del almacenamiento.',
            ]);

        }

        $pin = $this->vault->retrieve($entity->p12_password_ref);

        $this->logger->info('viafirma.download.base64_served', [
            'uuid'  => $uuid,
            'cr_id' => $certificateRequest->id,
            'vf_id' => $entity->id,
            'user'  => $userId,
        ]);

        return HttpResponseMessages::getResponse([
            'dataRecords' => [
                'data' => [
                    'p12_pin'      => $pin,
                    'p12_filename' => basename($entity->p12_storage_path),
                    'p12_base64'   => base64_encode($binary),
                ],
            ]
        ]);
    }
}

