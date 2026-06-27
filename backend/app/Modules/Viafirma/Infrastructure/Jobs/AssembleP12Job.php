<?php

declare(strict_types=1);

namespace App\Modules\Viafirma\Infrastructure\Jobs;

use App\Enums\CertificateRequestStatusEnum;
use App\Models\CertificateRequest;
use App\Models\ChangeHistory;
use App\Services\CertificateValidatorService;
use App\Modules\Viafirma\Domain\Contracts\CryptoServiceContract;
use App\Modules\Viafirma\Domain\Contracts\KeyVault;
use App\Modules\Viafirma\Domain\Enums\InternalState;
use App\Modules\Viafirma\Infrastructure\Persistence\Models\ViafirmaCertificateRequest;
use App\Modules\Viafirma\Infrastructure\Persistence\Models\ViafirmaStatusHistory;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use App\Modules\Viafirma\Infrastructure\Logging\SafePemLogger;
use App\Models\FileManager;

/**
 * Orquesta el ensamblaje del .p12 (V-405):
 *
 *  1. Recupera llave privada del KeyVault
 *  2. Lee el .p7b del storage
 *  3. Genera PIN CSPRNG (32 chars)
 *  4. CryptoService::assembleP12()
 *  5. Guarda .p12 en storage
 *  6. Guarda PIN cifrado en KeyVault
 *  7. Transiciona a ASSEMBLED (en $entity->state)
 *  8. Despacha notificación al cliente
 */
final class AssembleP12Job implements ShouldQueue, ShouldBeUnique
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;

    public int $tries   = 1;
    public int $timeout = 120;

    public function __construct(
        public readonly int $requestId,
    ) {}

    public function uniqueId(): string
    {
        return "viafirma-assemble-{$this->requestId}";
    }

    public function uniqueFor(): int
    {
        return 300;
    }

    /** @return string[] */
    public function tags(): array
    {
        return ["viafirma:assemble:{$this->requestId}"];
    }

    public function handle(
        CryptoServiceContract $crypto,
        KeyVault $vault,
        SafePemLogger $logger,
        \App\Services\Certificates\CertificateStoragePathResolver $pathResolver,
    ): void {
        $entity = ViafirmaCertificateRequest::with('state')->find($this->requestId);

        if ($entity === null) {
            $logger->warning('viafirma.assemble.entity_not_found', ['id' => $this->requestId]);
            return;
        }

        $state = $entity->state;

        // Guard: solo ensamblar si está en DOWNLOADED
        if ($state->internal_state !== InternalState::DOWNLOADED) {
            $logger->info('viafirma.assemble.skip_wrong_state', [
                'id'    => $entity->id,
                'state' => $state->internal_state->value,
            ]);
            return;
        }

        $logger->info('viafirma.assemble.start', ['id' => $entity->id]);

        try {
            // 1. Recuperar llave privada del KeyVault
            $privateKeyPem = $vault->retrieve($state->key_vault_ref);

            // 2. Leer P7B del storage (disco genérico de certificados)
            $disk      = $pathResolver->disk();
            $p7bBinary = Storage::disk($disk)->get($state->p7b_storage_path);

            if ($p7bBinary === null || $p7bBinary === '') {
                throw new \RuntimeException("P7B no encontrado en {$state->p7b_storage_path}");
            }

            // 3. Generar PIN CSPRNG
            $exportPin = Str::random(32);

            // 4. Ensamblar P12
            $friendlyName = $entity->cod_request ?? 'viafirma-cert';
            $p12Binary = $crypto->assembleP12(
                privateKeyPem:  $privateKeyPem,
                p7bDer:         $p7bBinary,
                friendlyName:   $friendlyName,
                exportPassword: $exportPin,
            );

            unset($privateKeyPem);

            // 4.bis Extraer validez real del certificado (validFrom / validTo) desde el P12 recién ensamblado.
            $validity = CertificateValidatorService::parseValidity($p12Binary, $exportPin);

            // 5. Guardar P12 en storage bajo base_path centralizado
            $basePath = $entity->certificateRequest->base_path;
            if (empty($basePath)) {
                throw new \RuntimeException(
                    "El base_path de la solicitud de certificado {$entity->certificate_request_id} no está configurado."
                );
            }

            // Crear ZIP con el P12
             $p12Filename = "{$entity->certificate_request_id}_{$entity->cod_request}.p12";
             $zipFilename = $basePath . '/' . "{$entity->certificate_request_id}_{$entity->cod_request}.zip";

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

            // 6. Guardar PIN cifrado en KeyVault
            $pinRef = $vault->store($exportPin, [
                'type'       => 'p12_pin',
                'request_id' => $entity->id,
            ]);

            // 7. Transicionar a ASSEMBLED
            $previousState = $state->internal_state;
            $state->p12_storage_path  = $zipFilename;
            $state->p12_password_ref  = $pinRef;
            $state->internal_state    = InternalState::ASSEMBLED;
            $state->assembled_at      = now();
            $state->save();

            ViafirmaStatusHistory::create([
                'viafirma_certificate_request_id' => $entity->id,
                'previous_state'                  => $previousState->value,
                'new_state'                       => InternalState::ASSEMBLED->value,
                'remote_status'                   => $state->remote_status,
                'raw_response'                    => ['action' => 'p12_assembled', 'p12_path' => $p12Filename],
                'attempt_number'                  => $state->poll_attempts,
                'occurred_at'                     => now(),
            ]);

            $logger->info('viafirma.assemble.success', [
                'id'       => $entity->id,
                'p12_path' => $p12Filename,
            ]);

            // 8. Transicionar a COMPLETED
            $state->internal_state = InternalState::COMPLETED;
            $state->save();

            ViafirmaStatusHistory::create([
                'viafirma_certificate_request_id' => $entity->id,
                'previous_state'                  => InternalState::ASSEMBLED->value,
                'new_state'                       => InternalState::COMPLETED->value,
                'remote_status'                   => $state->remote_status,
                'raw_response'                    => ['action' => 'completed'],
                'attempt_number'                  => $state->poll_attempts,
                'occurred_at'                     => now(),
            ]);

            $logger->info('viafirma.assemble.completed', ['id' => $entity->id]);

            // ── Registrar ZIP en file_managers ────────────────────────────────────────────
            $zipSize = Storage::disk($disk)->size($zipFilename);
            $zipLastModified = date('Y-m-d H:i:s', Storage::disk($disk)->lastModified($zipFilename));
            FileManager::updateOrCreate(
                [
                    'certificate_request_id' => $entity->certificate_request_id,
                    'file_name' => basename($zipFilename),
                ],
                [
                    'file_path' => $zipFilename,
                    'extension_file' => 'zip',
                    'mime_type' => 'application/zip',
                    'document_type' => 'CERTIFICATE',
                    'file_size' => $zipSize,
                    'last_modified' => $zipLastModified,
                    'status' => 'COMPLETED',
                ]
            );

            // ── Registrar referencia de llave privada en file_managers ────────────────────
            if ($state->key_vault_ref && $state->key_vault_ref !== 'PURGED') {
                // Obtener tamaño real de la llave privada desde el vault en S3
                $vaultPath = (string) config('viafirma.crypto.vault_path', 'viafirma/vault');
                $keyPath = rtrim($vaultPath, '/') . '/' . $state->key_vault_ref . '.bin';
                
                $keySize = 0;
                try {
                    $keySize = Storage::disk($disk)->size($keyPath);
                } catch (\Throwable) {
                    // Si no se puede obtener, dejar en 0
                }

                FileManager::updateOrCreate(
                    [
                        'certificate_request_id' => $entity->certificate_request_id,
                        'file_name' => 'private_key_reference',
                    ],
                    [
                        'file_path' => 'vault://' . $state->key_vault_ref,
                        'extension_file' => 'key',
                        'mime_type' => 'application/x-pkcs12-key',
                        'document_type' => 'PRIVATE_KEY',
                        'file_size' => $keySize,
                        'last_modified' => date('Y-m-d H:i:s'),
                        'status' => 'COMPLETED',   
                    ]
                );
            }

            // ── Actualizar la solicitud principal a PROCESSED + ciclo de vida ──────────────
            // certificate_requests es la fuente de verdad del ciclo de vida y vencimientos.
            // - issued_at      = validFrom real del X.509
            // - cert_valid_to  = validTo real del X.509 (Viafirma ~2 años; auditoría)
            // - expiration_date = vencimiento COMERCIAL = issued_at + life años
            $certificateRequest = CertificateRequest::find($entity->certificate_request_id);
            if ($certificateRequest !== null) {
                $life = (int) ($certificateRequest->life ?: 1);

                // Estado unificado vía mapper central (COMPLETED → PROCESSED).
                $certificateRequest->request_status  = InternalState::COMPLETED->toRequestStatus()->value;
                $certificateRequest->issued_at       = $validity['validFrom'];
                $certificateRequest->cert_valid_to   = $validity['validTo'];
                $certificateRequest->expiration_date = $validity['validFrom']->addYears($life);
                $certificateRequest->pin             = $exportPin;  // ✅ Guardar PIN en certificate_requests
                $certificateRequest->save();
            }

            ChangeHistory::create([
                'certificate_request_id' => $entity->certificate_request_id,
                'status'                 => CertificateRequestStatusEnum::PROCESSED->value,
                'comments'               => 'Certificado digital generado exitosamente y listo para descarga.',
                'user_of_change'         => 'SYSTEM',
                'user_id'                => null,
            ]);

        } catch (\Throwable $e) {
            $logger->error('viafirma.assemble.failed', [
                'id'    => $entity->id,
                'error' => $e->getMessage(),
            ]);

            // ── Marcar como FAILED_RECOVERABLE para que AutoRedownloadPendingViafirmaJob lo reintente ────
            // IMPORTANTE: NO purgar key_vault_ref — la llave privada debe persistir para reintentos.
            // Solo se purga cuando el trámite llega a estado terminal exitoso o por PurgeExpiredKeysJob.
            $state->internal_state     = InternalState::FAILED_RECOVERABLE;
            $state->last_error_code    = 'ASSEMBLE_FAILED';
            $state->last_error_message = substr($e->getMessage(), 0, 500);

            $state->save();

            ChangeHistory::create([
                'certificate_request_id' => $entity->certificate_request_id,
                'status'                 => CertificateRequestStatusEnum::PROCESSING->value,
                'comments'               => 'Error al generar el certificado digital — el sistema reintentará automáticamente.',
                'user_of_change'         => 'SYSTEM',
                'user_id'                => null,
            ]);

            throw $e;
        }
    }
}
