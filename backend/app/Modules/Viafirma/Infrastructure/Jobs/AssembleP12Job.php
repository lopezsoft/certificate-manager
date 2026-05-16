<?php

declare(strict_types=1);

namespace App\Modules\Viafirma\Infrastructure\Jobs;

use App\Modules\Viafirma\Domain\Contracts\CryptoServiceContract;
use App\Modules\Viafirma\Domain\Contracts\KeyVault;
use App\Modules\Viafirma\Domain\Enums\InternalState;
use App\Modules\Viafirma\Domain\Events\ViafirmaStatusChanged;
use App\Modules\Viafirma\Infrastructure\Persistence\Models\ViafirmaCertificateRequest;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Psr\Log\LoggerInterface;

/**
 * Orquesta el ensamblaje del .p12 (V-405):
 *
 *  1. Recupera llave privada del KeyVault
 *  2. Lee el .p7b del storage
 *  3. Genera PIN CSPRNG (32 chars)
 *  4. CryptoService::assembleP12()
 *  5. Guarda .p12 en storage
 *  6. Guarda PIN cifrado en KeyVault
 *  7. Transiciona a ASSEMBLED
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
        LoggerInterface $logger,
    ): void {
        $entity = ViafirmaCertificateRequest::find($this->requestId);

        if ($entity === null) {
            $logger->warning('viafirma.assemble.entity_not_found', ['id' => $this->requestId]);
            return;
        }

        // Guard: solo ensamblar si está en DOWNLOADED
        if ($entity->internal_state !== InternalState::DOWNLOADED) {
            $logger->info('viafirma.assemble.skip_wrong_state', [
                'id'    => $entity->id,
                'state' => $entity->internal_state->value,
            ]);
            return;
        }

        $logger->info('viafirma.assemble.start', ['id' => $entity->id]);

        try {
            // 1. Recuperar llave privada del KeyVault
            $privateKeyPem = $vault->retrieve($entity->key_vault_ref);

            // 2. Leer P7B del storage
            $p7bDisk = config('viafirma.storage.p7b_disk', 'local');
            $p7bBinary = Storage::disk($p7bDisk)->get($entity->p7b_storage_path);

            if ($p7bBinary === null || $p7bBinary === '') {
                throw new \RuntimeException("P7B no encontrado en {$entity->p7b_storage_path}");
            }

            // 3. Generar PIN CSPRNG
            $exportPin = Str::random(32);

            // 4. Ensamblar P12
            $friendlyName = $entity->cod_request ?? 'viafirma-cert';
            $p12Binary = $crypto->assembleP12(
                privateKeyPem: $privateKeyPem,
                p7bDer:        $p7bBinary,
                friendlyName:  $friendlyName,
                exportPassword: $exportPin,
            );

            // Limpiar llave de memoria
            // PHP no tiene sodium_memzero universal, pero la variable sale del scope pronto
            unset($privateKeyPem);

            // 5. Guardar P12 en storage
            $p12Disk = config('viafirma.storage.p12_disk', 'local');
            $p12Path = config('viafirma.storage.p12_path', 'viafirma/p12');
            $p12Filename = "{$p12Path}/{$entity->cod_request}.p12";

            Storage::disk($p12Disk)->put($p12Filename, $p12Binary);
            unset($p12Binary);

            // 6. Guardar PIN cifrado en KeyVault
            $pinRef = $vault->store($exportPin, [
                'type'       => 'p12_pin',
                'request_id' => $entity->id,
            ]);
            unset($exportPin);

            // 7. Transicionar a ASSEMBLED
            $previousState = $entity->internal_state;
            $entity->p12_storage_path = $p12Filename;
            $entity->p12_password_ref = $pinRef;
            $entity->internal_state   = InternalState::ASSEMBLED;
            $entity->assembled_at     = now();
            $entity->save();

            // Registrar en historial
            \App\Modules\Viafirma\Infrastructure\Persistence\Models\ViafirmaStatusHistory::create([
                'viafirma_certificate_request_id' => $entity->id,
                'previous_state'                  => $previousState->value,
                'new_state'                       => InternalState::ASSEMBLED->value,
                'remote_status'                   => $entity->remote_status,
                'raw_response'                    => ['action' => 'p12_assembled', 'p12_path' => $p12Filename],
                'attempt_number'                  => $entity->poll_attempts,
                'occurred_at'                     => now(),
            ]);

            $logger->info('viafirma.assemble.success', [
                'id'       => $entity->id,
                'p12_path' => $p12Filename,
            ]);

            // 8. Transicionar a COMPLETED
            $entity->internal_state = InternalState::COMPLETED;
            $entity->save();

            \App\Modules\Viafirma\Infrastructure\Persistence\Models\ViafirmaStatusHistory::create([
                'viafirma_certificate_request_id' => $entity->id,
                'previous_state'                  => InternalState::ASSEMBLED->value,
                'new_state'                       => InternalState::COMPLETED->value,
                'remote_status'                   => $entity->remote_status,
                'raw_response'                    => ['action' => 'completed'],
                'attempt_number'                  => $entity->poll_attempts,
                'occurred_at'                     => now(),
            ]);

            $logger->info('viafirma.assemble.completed', ['id' => $entity->id]);

        } catch (\Throwable $e) {
            $logger->error('viafirma.assemble.failed', [
                'id'      => $entity->id,
                'error'   => $e->getMessage(),
            ]);

            $entity->internal_state     = InternalState::FAILED;
            $entity->last_error_code    = 'ASSEMBLE_FAILED';
            $entity->last_error_message = substr($e->getMessage(), 0, 500);
            $entity->save();

            throw $e;
        }
    }
}
