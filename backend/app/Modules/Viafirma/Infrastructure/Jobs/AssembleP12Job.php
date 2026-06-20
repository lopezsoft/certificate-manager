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

            // 5. Guardar P12 en storage (ruteo genérico agnóstico de proveedor)
            $p12Filename = $pathResolver->path('viafirma', 'p12', "{$entity->certificate_request_id}_{$entity->cod_request}.p12");

            Storage::disk($disk)->put($p12Filename, $p12Binary);
            unset($p12Binary);

            // 6. Guardar PIN cifrado en KeyVault
            $pinRef = $vault->store($exportPin, [
                'type'       => 'p12_pin',
                'request_id' => $entity->id,
            ]);
            unset($exportPin);

            // 7. Transicionar a ASSEMBLED
            $previousState = $state->internal_state;
            $state->p12_storage_path  = $p12Filename;
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

            $state->internal_state     = InternalState::FAILED;
            $state->last_error_code    = 'ASSEMBLE_FAILED';
            $state->last_error_message = substr($e->getMessage(), 0, 500);

            // Marcar referencias del vault como PURGED para evitar referencias huérfanas.
            if ($state->key_vault_ref && $state->key_vault_ref !== 'PURGED') {
                $state->key_vault_ref = 'PURGED';
            }
            if ($state->p12_password_ref && $state->p12_password_ref !== 'PURGED') {
                $state->p12_password_ref = 'PURGED';
            }

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
