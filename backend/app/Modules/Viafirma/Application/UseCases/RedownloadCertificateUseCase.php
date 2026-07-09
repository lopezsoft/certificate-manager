<?php

declare(strict_types=1);

namespace App\Modules\Viafirma\Application\UseCases;

use App\Enums\CertificateRequestStatusEnum;
use App\Events\CertificateStatusChanged;
use App\Models\ChangeHistory;
use App\Models\FileManager;
use App\Modules\Viafirma\Application\DTOs\RedownloadResultDto;
use App\Modules\Viafirma\Domain\Contracts\CryptoServiceContract;
use App\Modules\Viafirma\Domain\Contracts\KeyVault;
use App\Modules\Viafirma\Domain\Contracts\ViafirmaClient;
use App\Modules\Viafirma\Domain\Enums\InternalState;
use App\Modules\Viafirma\Domain\Exceptions\ViafirmaException;
use App\Modules\Viafirma\Infrastructure\Persistence\Models\ViafirmaCertificateRequest;
use App\Modules\Viafirma\Infrastructure\Persistence\Models\ViafirmaStatusHistory;
use App\Services\CertificateValidatorService;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use App\Modules\Viafirma\Infrastructure\Logging\SafePemLogger;

/**
 * RedownloadCertificateUseCase — re-descarga el P7B de Viafirma y regenera el P12.
 *
 * Puede ser invocado por:
 *  - Un ADMIN vía POST /api/v1/certificate-request/{id}/issuance/redownload
 *  - El sistema automáticamente vía AutoRedownloadPendingViafirmaJob (adminUserId = null)
 *
 * Flujo de ejecución (§3.1 del spec):
 *   1. Buscar ViafirmaCertificateRequest por certificate_request_id  → 404 si no existe
 *   2. Consultar estado remoto en Viafirma                           → 502 si falla HTTP
 *   3. Validar estado remoto (Generated_Not_Downloaded|Generated_And_Downloaded) → 409
 *   4. Validar que key_vault_ref no esté purgada                    → 422
 *   5. Descargar P7B (sobrescribir)
 *   6. Generar nuevo PIN CSPRNG (32 chars)
 *   7. Recuperar llave privada del KeyVault
 *   8. Ensamblar nuevo P12
 *   9. Guardar P12 en storage (sobrescribir)
 *  10. Destruir PIN anterior del vault (si existe y no es PURGED)
 *  11. Guardar nuevo PIN en KeyVault
 *  12. Actualizar ViafirmaCertificateRequestState → ASSEMBLED
 *  13. Registrar en viafirma_status_history
 *  14. Registrar en change_histories
 *  15. Guardar P12 en file_managers (crear o actualizar)
 *  16. Retornar RedownloadResultDto { pin, download_url, expires_at, viafirma_id, ... }
 *
 * NOTA: Tras la normalización, los campos de estado se encuentran en
 * $entity->state (ViafirmaCertificateRequestState).
 */
final class RedownloadCertificateUseCase
{
    public function __construct(
        private readonly ViafirmaClient      $client,
        private readonly CryptoServiceContract $crypto,
        private readonly KeyVault            $vault,
        private readonly SafePemLogger       $logger,
        private readonly \App\Services\Certificates\CertificateStoragePathResolver $pathResolver,
    ) {}

    /**
     * @param int $certificateRequestId  ID de certificate_requests.id
     * @param int|null $adminUserId      ID del usuario admin; null cuando lo invoca el sistema (job automático)
     *
     * @throws ViafirmaException Con código HTTP embebido (409, 422)
     * @throws \Illuminate\Database\Eloquent\ModelNotFoundException 404
     * @throws \Throwable 502 cuando Viafirma falla
     */
    public function handle(int $certificateRequestId, ?int $adminUserId): RedownloadResultDto
    {
        // ── 1. Buscar entidad ────────────────────────────────────────────────
        $entity = ViafirmaCertificateRequest::where('certificate_request_id', $certificateRequestId)
            ->with(['state', 'certificateRequest'])
            ->firstOrFail();

        $state = $entity->state;

        $this->logger->info('viafirma.redownload.start', [
            'viafirma_id'            => $entity->id,
            'certificate_request_id' => $certificateRequestId,
            'admin_user_id'          => $adminUserId,
        ]);

        // ── 2. Consultar estado remoto (siempre — no confiar en internal_state) ──
        $statusResult = $this->client->getStatus($entity->cod_request);

        // ── 3. Validar estado remoto ─────────────────────────────────────────
        if (!$statusResult->status->isReadyToDownload() && !$statusResult->status->isTerminalOk()) {
            throw new ViafirmaException(
                "El estado remoto '{$statusResult->status->value}' no permite re-descarga. " .
                "Solo se permite en estados: Generated_Not_Downloaded, Generated_And_Downloaded.",
                409,
            );
        }

        // ── 4. Validar que la llave privada no fue purgada ───────────────────
        if (empty($state->key_vault_ref) || $state->key_vault_ref === 'PURGED') {
            throw new ViafirmaException(
                'La llave privada de esta solicitud fue purgada y no puede regenerarse el P12. ' .
                'Se requiere una nueva emisión.',
                422,
            );
        }

        // ── 5. Descargar P7B nuevamente ──────────────────────────────────────
        $p7bBinary = $this->client->downloadP7b($entity->public_id);

        $disk    = $this->pathResolver->disk();
        $p7bPath = $state->p7b_storage_path
            ?? $this->pathResolver->path('viafirma', 'p7b', $entity->cod_request . '.p7b');

        Storage::disk($disk)->put($p7bPath, $p7bBinary);

        // ── 5.bis Obtener código de revocación desde Viafirma ──────────────────
        $revocationCode = $this->client->getRevocationCode($entity->cod_request);

        $this->logger->info('viafirma.redownload.p7b_saved', [
            'viafirma_id' => $entity->id,
            'path'        => $p7bPath,
            'size'        => strlen($p7bBinary),
            'revocation_code' => $revocationCode,
        ]);

        // ── 6. Generar nuevo PIN CSPRNG ──────────────────────────────────────
        $newPin = Str::random(32);

        // ── 7. Recuperar llave privada del KeyVault ──────────────────────────
        $privateKeyPem = $this->vault->retrieve($state->key_vault_ref);

        // ── 8. Ensamblar nuevo P12 ───────────────────────────────────────────
        $friendlyName = $entity->cod_request ?? 'viafirma-cert';
        $p12Binary = $this->crypto->assembleP12(
            privateKeyPem:  $privateKeyPem,
            p7bDer:         $p7bBinary,
            friendlyName:   $friendlyName,
            exportPassword: $newPin,
        );

        unset($privateKeyPem, $p7bBinary);

        // ── 8.bis Extraer validez real (validFrom / validTo) del P12 reensamblado ──
        $validity = CertificateValidatorService::parseValidity($p12Binary, $newPin);

        $cr = $entity->certificateRequest;

        // ── 9. Guardar P12 en storage (sobrescribir) ─────────────────────────
        // Usar base_path de certificate_requests como base para guardar el ZIP
        if (empty($cr->base_path)) {
            throw new ViafirmaException(
                'El base_path de la solicitud de certificado no está configurado.',
                422,
            );
        }
        
        $basePath = $cr->base_path;
        $p12Filename = "{$entity->certificate_request_id}_{$entity->cod_request}.p12";
        $zipFilename = $basePath . '/' . "{$entity->certificate_request_id}_{$entity->cod_request}.zip";

        // Delete the old file if the path has changed (e.g. migration to new naming convention)
        if ($state->p12_storage_path && $state->p12_storage_path !== $zipFilename) {
            Storage::disk($disk)->delete($state->p12_storage_path);
        }

        // ── Guardar ZIP en local (como estaba originalmente) ────────────────────────────
         $zip = new \ZipArchive();
         $zipPath = Storage::path($zipFilename);

         // Crear directorio si no existe
         $zipDir = dirname($zipPath);
         if (!is_dir($zipDir)) {
             mkdir($zipDir, 0755, true);
         }

         $openResult = $zip->open($zipPath, \ZipArchive::CREATE | \ZipArchive::OVERWRITE);
         if ($openResult !== true) {
             throw new \RuntimeException("No se pudo crear el ZIP: error {$openResult} en {$zipPath}");
         }

         $addResult = $zip->addFromString($p12Filename, $p12Binary);
         if (!$addResult) {
             $zip->close();
             throw new \RuntimeException("No se pudo agregar P12 al ZIP");
         }

         $closeResult = $zip->close();
         if (!$closeResult) {
             throw new \RuntimeException("No se pudo cerrar el ZIP correctamente");
         }

         // Verificar que el ZIP se creó
         if (!file_exists($zipPath)) {
             throw new \RuntimeException("El ZIP no existe después de crearlo: {$zipPath}");
         }

         unset($p12Binary);

         // ── Si el disco configurado NO es local, subir a S3 y eliminar el local ────────
         if ($disk !== 'local') {
             Storage::disk($disk)->put($zipFilename, file_get_contents($zipPath));
             @unlink($zipPath);
         }

        $this->logger->info('viafirma.redownload.p12_saved', [
            'viafirma_id' => $entity->id,
            'path'        => $zipFilename,
        ]);

        // ── 9.bis Guardar P7B redescargado en file_managers ──────────────────
        try {
            $p7bSize = Storage::disk($disk)->size($p7bPath);
            $p7bLastModified = date('Y-m-d H:i:s', Storage::disk($disk)->lastModified($p7bPath));
        } catch (\Throwable $e) {
            $this->logger->warning('viafirma.redownload.p7b_metadata_error', [
                'viafirma_id' => $entity->id,
                'p7b_path'    => $p7bPath,
                'error'       => $e->getMessage(),
            ]);
            $p7bSize = 0;
            $p7bLastModified = date('Y-m-d H:i:s');
        }

        FileManager::updateOrCreate(
            [
                'certificate_request_id' => $certificateRequestId,
                'file_name'              => basename($p7bPath),
            ],
            [
                'file_path'      => $p7bPath,
                'file_name'      => basename($p7bPath),
                'extension_file' => 'p7b',
                'mime_type'      => 'application/pkcs7-mime',
                'file_size'      => $p7bSize,
                'last_modified'  => $p7bLastModified,
                'status'         => 'COMPLETED',
                'document_type'  => 'P7B_CERTIFICATE',
            ]
        );

        // ── 10. Destruir PIN anterior del vault ──────────────────────────────
        $oldPinRef = $state->p12_password_ref;
        if (!empty($oldPinRef) && $oldPinRef !== 'PURGED') {
            try {
                $this->vault->destroy($oldPinRef);
            } catch (\Throwable $e) {
                $this->logger->warning('viafirma.redownload.old_pin_destroy_failed', [
                    'viafirma_id' => $entity->id,
                    'error'       => $e->getMessage(),
                ]);
            }
        }

        // ── 11. Guardar nuevo PIN en KeyVault ────────────────────────────────
        $newPinRef = $this->vault->store($newPin, [
            'type'       => 'p12_pin',
            'request_id' => $entity->id,
            'action'     => 'admin_redownload',
        ]);

        // ── 12. Actualizar estado ─────────────────────────────────────────────
        $previousState = $state->internal_state;

        $state->p7b_storage_path      = $p7bPath;
        $state->p12_storage_path      = $zipFilename;
        $state->p12_password_ref      = $newPinRef;
        $state->revocation_request_code = $revocationCode;  // ✅ Guardar código de revocación
        $state->internal_state        = InternalState::COMPLETED;  // ✅ Transicionar a COMPLETED
        $state->remote_status         = $statusResult->status->value;
        $state->last_status_response  = $statusResult->raw ?: null;  // ✅ Actualizar respuesta remota
        $state->downloaded_at         = now();
        $state->assembled_at          = now();
        $state->last_error_code       = null;
        $state->last_error_message    = null;
        $state->save();

        // ── 13. Registrar en viafirma_status_history ──────────────────────────
        ViafirmaStatusHistory::create([
            'viafirma_certificate_request_id' => $entity->id,
            'previous_state'                  => $previousState->value,
            'new_state'                       => InternalState::ASSEMBLED->value,
            'remote_status'                   => $statusResult->status->value,
            'raw_response'                    => [
                'action'         => 'admin_redownload',
                'admin_user_id'  => $adminUserId,
                'p7b_path'       => $p7bPath,
                'p12_path'       => $zipFilename,
                'remote_status'  => $statusResult->status->value,
            ],
            'attempt_number' => $state->poll_attempts,
            'occurred_at'    => now(),
        ]);

        // ── 14. Actualizar ciclo de vida en certificate_requests + change_histories ──
        if ($cr) {
            // certificate_requests es la fuente de verdad del ciclo de vida y vencimientos.
            $life = (int) ($cr->life ?: 1);
            $cr->request_status  = CertificateRequestStatusEnum::PROCESSED->value;
            $cr->issued_at       = $validity['validFrom'];
            $cr->cert_valid_to   = $validity['validTo'];
            $cr->expiration_date = $validity['validFrom']->addYears($life);
            $cr->pin             = $newPin;
            $cr->save();

            ChangeHistory::create([
                'certificate_request_id' => $cr->id,
                'user_id'                => $adminUserId,
                'user_of_change'         => $adminUserId ?? 'SYSTEM',
                'status'                 => CertificateRequestStatusEnum::PROCESSED->value,
                'comments'               => 'Certificado P12 regenerado. ' .
                                            "Estado remoto Viafirma: {$statusResult->status->value}.",
            ]);

            // Disparar evento para notificaciones y webhooks
            event(new CertificateStatusChanged(
                certificateRequestId: $cr->id,
                companyId: (int) $cr->company_id,
                previousStatus: CertificateRequestStatusEnum::PROCESSING->value,
                newStatus: CertificateRequestStatusEnum::PROCESSED->value,
                userId: $adminUserId ?? 0,
                comment: 'Certificado P12 regenerado exitosamente por Viafirma.',
            ));
        }

        // ── 15. Guardar P12 en file_managers (crear o actualizar) ────────────
        try {
            $fileSize = Storage::disk($disk)->size($zipFilename);
            $lastModified = date('Y-m-d H:i:s', Storage::disk($disk)->lastModified($zipFilename));
        } catch (\Throwable $e) {
            $this->logger->warning('viafirma.redownload.file_metadata_error', [
                'viafirma_id' => $entity->id,
                'zip_path'    => $zipFilename,
                'error'       => $e->getMessage(),
            ]);
            $fileSize = 0;
            $lastModified = date('Y-m-d H:i:s');
        }

        FileManager::updateOrCreate(
            [
                'certificate_request_id' => $certificateRequestId,
                'file_name'              => basename($zipFilename),
            ],
            [
                'file_path'              => $zipFilename,
                'file_name'              => basename($zipFilename),
                'extension_file'         => 'zip',
                'mime_type'              => 'application/zip',
                'file_size'              => $fileSize,
                'last_modified'          => $lastModified,
                'status'                 => 'COMPLETED',
                'document_type'          => 'CERTIFICATE',
            ]
        );

        $this->logger->info('viafirma.redownload.success', [
            'viafirma_id'   => $entity->id,
            'remote_status' => $statusResult->status->value,
            'zip_path'      => $zipFilename,
        ]);

        // ── 16. Retornar resultado ────────────────────────────────────────────
        $downloadUrl = route('v1.certificate-request.issuance.download', ['uuid' => $cr?->uuid]);

        return new RedownloadResultDto(
            pin:           $newPin,
            downloadUrl:   $downloadUrl,
            viafirmaId:    $entity->id,
            internalState: InternalState::COMPLETED->value,
            remoteStatus:  $statusResult->status->value,
            expiresAt:     $cr?->expiration_date_formatted,
        );
    }
}
