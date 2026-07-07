<?php

declare(strict_types=1);

namespace App\Console\Commands\Certificates;

use App\Enums\CertificateRequestStatusEnum;
use App\Models\CertificateRequest;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\Console\Attribute\AsCommand;

/**
 * Migra a S3 los archivos de los certificados del OTRO proveedor (no Viafirma).
 *
 * Viafirma es el proveedor nuevo: no tiene certificados emitidos que migrar y escribe
 * directo a S3 desde su emisión. Por eso esta migración es EXCLUSIVA del proveedor legacy.
 *
 * Estrategia (bajo riesgo): se copian los archivos a la MISMA ruta relativa en el disco
 * destino (S3). No se reescriben rutas en BD. Tras migrar, basta con poner
 * CERT_LEGACY_DISK=s3 para que las lecturas usen S3 (ver config/certificates.php).
 *
 * Criterio de selección:
 *   - expiration_date es NULL o expiration_date > now() (vigentes + sin fecha)
 *   - SIN viafirma_certificate_requests asociado (otro proveedor)
 *   - Incluye TODOS los estados de request_status
 *
 * Uso:
 *   php artisan certificates:migrate-legacy-to-s3            # dry-run (no copia)
 *   php artisan certificates:migrate-legacy-to-s3 --apply
 *   php artisan certificates:migrate-legacy-to-s3 --apply --from=attachment --to=s3 --force
 */
#[AsCommand(name: 'certificates:migrate-legacy-to-s3')]
final class MigrateLegacyCertificatesToS3Command extends Command
{
    protected $signature = 'certificates:migrate-legacy-to-s3
                            {--apply  : Copia los archivos de verdad (sin esto es dry-run)}
                            {--from=attachment : Disco origen (legacy)}
                            {--to=s3  : Disco destino}
                            {--force  : Necesario para entornos no locales}';

    protected $description = 'Migra a S3 los archivos de certificados vigentes del otro proveedor (no Viafirma).';

    public function handle(): int
    {
        $apply    = (bool) $this->option('apply');
        $fromDisk = (string) $this->option('from');
        $toDisk   = (string) $this->option('to');
        $force    = (bool) $this->option('force');

        if (!$apply) {
            $this->warn('⚠️  Modo dry-run. Pasa --apply para copiar de verdad.');
        }
        if ($apply && !app()->environment('local') && !$force) {
            $this->error('❌ Estás fuera de "local". Debes añadir --force para confirmar.');
            return self::FAILURE;
        }

        $from = Storage::disk($fromDisk);
        $to   = Storage::disk($toDisk);

        // Solicitudes legacy para migrar a S3.
        // Migra a S3: todos los certificados legacy (independiente de estado) que tengan archivos.
        // Nota: Viafirma es nuevo en esta versión, por lo que no hay registros históricos que filtrar.
        //
        // Criterio: certificados con expiration_date NULL (sin procesar) O vigentes (expiration_date > now)
        $query = CertificateRequest::query()
            ->where(function ($q) {
                $q->whereNull('expiration_date')
                  ->orWhere('expiration_date', '>', now());
            })
            ->with('files');

        $total      = $query->count();
        $copied     = 0;
        $skipped    = 0;
        $missing    = 0;
        $reqHandled = 0;

        $this->info("Solicitudes legacy (vigentes + sin fecha) a procesar: {$total} (origen='{$fromDisk}' → destino='{$toDisk}').");

        $query->chunkById(100, function ($requests) use ($from, $to, $apply, &$copied, &$skipped, &$missing, &$reqHandled) {
            foreach ($requests as $request) {
                $reqHandled++;
                foreach ($request->files as $file) {
                    $path = $file->file_path;
                    if (empty($path)) {
                        continue;
                    }
                    if (!$from->exists($path)) {
                        $missing++;
                        $this->line("  · [FALTA en origen] cr={$request->id} {$path}");
                        continue;
                    }
                    if ($to->exists($path)) {
                        $skipped++;
                        continue;
                    }
                    if ($apply) {
                        $to->put($path, $from->get($path));
                    }
                    $copied++;
                    $this->line(($apply ? '  ✓ [COPIADO]' : '  ↪ [COPIARÍA]') . " cr={$request->id} {$path}");
                }
            }
        });

        $this->newLine();
        $this->info("Resumen: solicitudes={$reqHandled}, copiados=" . ($apply ? $copied : "{$copied} (dry-run)") .
            ", ya-presentes={$skipped}, faltantes-en-origen={$missing}.");

        if ($apply) {
            $this->line('📝 Tras verificar, pon CERT_LEGACY_DISK=' . $toDisk . ' para que las lecturas usen el disco destino.');
        }

        return self::SUCCESS;
    }
}
