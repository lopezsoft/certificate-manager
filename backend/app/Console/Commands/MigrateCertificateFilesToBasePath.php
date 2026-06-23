<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\FileManager;
use App\Modules\Viafirma\Domain\Enums\InternalState;
use App\Modules\Viafirma\Infrastructure\Persistence\Models\ViafirmaCertificateRequest;
use App\Modules\Viafirma\Infrastructure\Persistence\Models\ViafirmaCertificateRequestState;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;
use ZipArchive;

/**
 * Migración de archivos de certificados a base_path centralizado.
 *
 * Migra archivos P7B y P12 de ubicaciones dispersas (viafirma/p7b, viafirma/p12)
 * a la ruta centralizada bajo certificate_requests.base_path.
 *
 * Uso:
 *   php artisan migrate:certificate-files-to-base-path
 *   php artisan migrate:certificate-files-to-base-path --dry-run
 *   php artisan migrate:certificate-files-to-base-path --rollback
 */
final class MigrateCertificateFilesToBasePath extends Command
{
    protected $signature = 'migrate:certificate-files-to-base-path
                            {--dry-run : Simular migración sin hacer cambios}
                            {--rollback : Revertir migración}';

    protected $description = 'Migra archivos de certificados a base_path centralizado';

    private bool $dryRun = false;
    private bool $rollback = false;
    private int $migratedCount = 0;
    private int $errorCount = 0;

    public function handle(): int
    {
        $this->dryRun = (bool) $this->option('dry-run');
        $this->rollback = (bool) $this->option('rollback');

        $this->info('═══════════════════════════════════════════════════════════');
        $this->info('Migración de Archivos de Certificados a Base Path');
        $this->info('═══════════════════════════════════════════════════════════');

        if ($this->dryRun) {
            $this->warn('⚠️  MODO DRY-RUN: No se realizarán cambios');
        }

        if ($this->rollback) {
            return $this->performRollback();
        }

        return $this->performMigration();
    }

    private function performMigration(): int
    {
        try {
            // Fase 1: Validación
            $this->info("\n📋 Fase 1: Validación...");
            if (!$this->validateMigration()) {
                return self::FAILURE;
            }

            // Fase 2: Migración de archivos
            $this->info("\n📦 Fase 2: Migrando archivos...");
            $this->migrateFiles();

            // Fase 3: Actualizar file_managers
            $this->info("\n📝 Fase 3: Actualizando file_managers...");
            $this->updateFileManagers();

            // Fase 4: Resumen
            $this->printSummary();

            return self::SUCCESS;
        } catch (\Throwable $e) {
            $this->error("❌ Error durante la migración: {$e->getMessage()}");
            return self::FAILURE;
        }
    }

    private function validateMigration(): bool
    {
        $this->line('  ✓ Validando certificados con base_path configurado...');

        $certificatesWithoutBasePath = ViafirmaCertificateRequest::query()
            ->whereHas('certificateRequest', function ($q) {
                $q->whereNull('base_path')->orWhere('base_path', '');
            })
            ->count();

        if ($certificatesWithoutBasePath > 0) {
            $this->error("  ✗ Hay {$certificatesWithoutBasePath} certificados sin base_path configurado");
            return false;
        }

        $this->line('  ✓ Todos los certificados tienen base_path configurado');
        return true;
    }

    private function migrateFiles(): void
    {
        $disk = 'local'; // Ajustar según configuración
        $states = ViafirmaCertificateRequestState::query()
            ->whereIn('internal_state', [
                InternalState::ASSEMBLED->value,
                InternalState::COMPLETED->value,
            ])
            ->with('viafirmaCertificateRequest.certificateRequest')
            ->get();

        $this->line("  Procesando {$states->count()} certificados...");

        foreach ($states as $state) {
            try {
                $basePath = $state->viafirmaCertificateRequest->certificateRequest->base_path;

                if (empty($basePath)) {
                    $this->warn("  ⚠️  Certificado {$state->viafirma_certificate_request_id} sin base_path");
                    continue;
                }

                // Crear directorio si no existe
                if (!Storage::disk($disk)->exists($basePath)) {
                    Storage::disk($disk)->makeDirectory($basePath, 0755, true);
                }

                // Migrar P7B
                if ($state->p7b_storage_path && Storage::disk($disk)->exists($state->p7b_storage_path)) {
                    $this->migrateP7b($state, $disk, $basePath);
                }

                // Migrar P12 (crear ZIP si es necesario)
                if ($state->p12_storage_path && Storage::disk($disk)->exists($state->p12_storage_path)) {
                    $this->migrateP12($state, $disk, $basePath);
                }

                $this->migratedCount++;
            } catch (\Throwable $e) {
                $this->error("  ✗ Error migrando certificado {$state->viafirma_certificate_request_id}: {$e->getMessage()}");
                $this->errorCount++;
            }
        }
    }

    private function migrateP7b(ViafirmaCertificateRequestState $state, string $disk, string $basePath): void
    {
        $oldPath = $state->p7b_storage_path;
        $newPath = $basePath . '/' . basename($oldPath);

        if ($oldPath === $newPath) {
            return; // Ya está en el lugar correcto
        }

        $content = Storage::disk($disk)->get($oldPath);

        if (!$this->dryRun) {
            Storage::disk($disk)->put($newPath, $content);
            Storage::disk($disk)->delete($oldPath);
            $state->p7b_storage_path = $newPath;
            $state->save();
        }

        $this->line("  ✓ P7B migrado: {$oldPath} → {$newPath}");
    }

    private function migrateP12(ViafirmaCertificateRequestState $state, string $disk, string $basePath): void
    {
        $oldPath = $state->p12_storage_path;
        $p12Filename = "{$state->viafirma_certificate_request_id}_{$state->viafirmaCertificateRequest->cod_request}.p12";
        $zipFilename = $basePath . '/' . "{$state->viafirma_certificate_request_id}_{$state->viafirmaCertificateRequest->cod_request}.zip";

        // Si ya es un ZIP, solo mover
        if (str_ends_with($oldPath, '.zip')) {
            if ($oldPath === $zipFilename) {
                return; // Ya está en el lugar correcto
            }

            $content = Storage::disk($disk)->get($oldPath);

            if (!$this->dryRun) {
                Storage::disk($disk)->put($zipFilename, $content);
                Storage::disk($disk)->delete($oldPath);
                $state->p12_storage_path = $zipFilename;
                $state->save();
            }

            $this->line("  ✓ ZIP migrado: {$oldPath} → {$zipFilename}");
            return;
        }

        // Si es P12 sin comprimir, crear ZIP
        $p12Content = Storage::disk($disk)->get($oldPath);

        if (!$this->dryRun) {
            // Crear ZIP
            $zip = new ZipArchive();
            $zipPath = Storage::path($zipFilename);
            if ($zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) === true) {
                $zip->addFromString($p12Filename, $p12Content);
                $zip->close();
            }

            // Eliminar P12 antiguo
            Storage::disk($disk)->delete($oldPath);

            // Actualizar estado
            $state->p12_storage_path = $zipFilename;
            $state->save();
        }

        $this->line("  ✓ P12 comprimido y migrado: {$oldPath} → {$zipFilename}");
    }

    private function updateFileManagers(): void
    {
        $states = ViafirmaCertificateRequestState::query()
            ->whereIn('internal_state', [
                InternalState::ASSEMBLED->value,
                InternalState::COMPLETED->value,
            ])
            ->with('viafirmaCertificateRequest.certificateRequest')
            ->get();

        foreach ($states as $state) {
            try {
                $certificateRequestId = $state->viafirmaCertificateRequest->certificateRequest->id;

                // Registrar P7B
                if ($state->p7b_storage_path) {
                    $p7bSize = 0;
                    if (Storage::disk('local')->exists($state->p7b_storage_path)) {
                        $p7bSize = Storage::disk('local')->size($state->p7b_storage_path);
                    }
                    FileManager::updateOrCreate(
                        [
                            'certificate_request_id' => $certificateRequestId,
                            'file_path' => $state->p7b_storage_path,
                        ],
                        [
                            'file_name' => basename($state->p7b_storage_path),
                            'extension_file' => 'p7b',
                            'mime_type' => 'application/pkcs7-mime',
                            'document_type' => 'P7B_CERTIFICATE',
                            'file_size' => $p7bSize,
                            'status' => 'COMPLETED',
                        ]
                    );
                }

                // Registrar ZIP (P12 comprimido)
                if ($state->p12_storage_path) {
                    $zipSize = 0;
                    if (Storage::disk('local')->exists($state->p12_storage_path)) {
                        $zipSize = Storage::disk('local')->size($state->p12_storage_path);
                    }
                    FileManager::updateOrCreate(
                        [
                            'certificate_request_id' => $certificateRequestId,
                            'file_path' => $state->p12_storage_path,
                        ],
                        [
                            'file_name' => basename($state->p12_storage_path),
                            'extension_file' => 'zip',
                            'mime_type' => 'application/zip',
                            'document_type' => 'CERTIFICATE',
                            'file_size' => $zipSize,
                            'status' => 'COMPLETED',
                        ]
                    );
                }

                // Registrar referencia de llave privada
                if ($state->key_vault_ref && $state->key_vault_ref !== 'PURGED') {
                    FileManager::updateOrCreate(
                        [
                            'certificate_request_id' => $certificateRequestId,
                            'file_path' => 'vault://' . $state->key_vault_ref,
                        ],
                        [
                            'file_name' => 'private_key_reference',
                            'extension_file' => 'key',
                            'mime_type' => 'application/x-pkcs12-key',
                            'document_type' => 'PRIVATE_KEY',
                            'file_size' => 0,
                            'status' => 'ACTIVE',
                        ]
                    );
                }
            } catch (\Throwable $e) {
                $this->error("  ✗ Error actualizando file_managers para certificado {$state->viafirma_certificate_request_id}: {$e->getMessage()}");
                $this->errorCount++;
            }
        }
    }

    private function performRollback(): int
    {
        $this->warn('⚠️  Rollback no implementado aún');
        return self::FAILURE;
    }

    private function printSummary(): void
    {
        $this->info("\n═══════════════════════════════════════════════════════════");
        $this->info("📊 Resumen de Migración");
        $this->info("═══════════════════════════════════════════════════════════");
        $this->line("  ✓ Certificados migrados: {$this->migratedCount}");
        $this->line("  ✗ Errores: {$this->errorCount}");

        if ($this->dryRun) {
            $this->warn("\n⚠️  Modo DRY-RUN: No se realizaron cambios");
        } else {
            $this->info("\n✅ Migración completada exitosamente");
        }
    }
}
