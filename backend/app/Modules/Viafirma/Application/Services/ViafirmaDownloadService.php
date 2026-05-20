<?php

declare(strict_types=1);

namespace App\Modules\Viafirma\Application\Services;

use App\Modules\Viafirma\Domain\Contracts\KeyVault;
use App\Modules\Viafirma\Domain\Contracts\ViafirmaCertificateRequestRepositoryContract;
use App\Modules\Viafirma\Domain\Enums\InternalState;
use App\Modules\Viafirma\Infrastructure\Persistence\Models\ViafirmaCertificateRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Storage;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Servicio que encapsula la lógica de descarga del P12 ensamblado.
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
        private readonly LoggerInterface $logger,
    ) {}

    /**
     * Devuelve metadata + URL temporal firmada (24h) + PIN del P12.
     */
    public function metadataFor(int $certificateRequestId, ?int $userId = null): JsonResponse
    {
        $entity = $this->repository->findByCertificateRequestId($certificateRequestId);

        if ($entity === null) {
            return response()->json([
                'success' => false,
                'message' => 'No existe un trámite Viafirma para esta solicitud.',
            ], 404);
        }

        if (!$this->canDownload($entity)) {
            return response()->json([
                'success' => false,
                'message' => "El certificado no está disponible para descarga en estado {$entity->internal_state?->value}.",
            ], 409);
        }

        if (empty($entity->p12_storage_path)) {
            return response()->json([
                'success' => false,
                'message' => 'El archivo P12 no se ha generado aún.',
            ], 409);
        }

        if (empty($entity->p12_password_ref) || $entity->p12_password_ref === 'PURGED') {
            return response()->json([
                'success' => false,
                'message' => 'El PIN del certificado ha sido purgado. Contacte soporte.',
            ], 410);
        }

        $pin = $this->vault->retrieve($entity->p12_password_ref);
        $disk = (string) config('viafirma.storage.p12_disk', 'local');

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
            'cr_id'  => $certificateRequestId,
            'vf_id'  => $entity->id,
            'user'   => $userId,
        ]);

        return response()->json([
            'success'      => true,
            'p12_pin'      => $pin,
            'p12_filename' => basename($entity->p12_storage_path),
            'download_url' => $temporaryUrl,
            'expires_at'   => now()->addHours(24)->toISOString(),
        ]);
    }

    /**
     * Streaming binario del P12.
     */
    public function streamFor(int $certificateRequestId, ?int $userId = null): StreamedResponse|JsonResponse
    {
        $entity = $this->repository->findByCertificateRequestId($certificateRequestId);

        if ($entity === null) {
            return response()->json([
                'success' => false,
                'message' => 'No existe un trámite Viafirma para esta solicitud.',
            ], 404);
        }

        if (!$this->canDownload($entity)) {
            return response()->json([
                'success' => false,
                'message' => "Estado {$entity->internal_state?->value} no permite descarga.",
            ], 409);
        }

        if (empty($entity->p12_storage_path)) {
            return response()->json([
                'success' => false,
                'message' => 'Archivo P12 no disponible.',
            ], 409);
        }

        $disk     = (string) config('viafirma.storage.p12_disk', 'local');
        $filename = basename($entity->p12_storage_path);

        $this->logger->info('viafirma.download.file_streamed', [
            'cr_id' => $certificateRequestId,
            'vf_id' => $entity->id,
            'user'  => $userId,
        ]);

        return Storage::disk($disk)->download($entity->p12_storage_path, $filename, [
            'Content-Type'        => 'application/x-pkcs12',
            'Content-Disposition' => "attachment; filename=\"{$filename}\"",
        ]);
    }

    private function canDownload(ViafirmaCertificateRequest $entity): bool
    {
        return in_array($entity->internal_state, [
            InternalState::ASSEMBLED,
            InternalState::COMPLETED,
        ], true);
    }
}

