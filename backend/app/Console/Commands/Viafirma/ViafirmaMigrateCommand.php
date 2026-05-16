<?php

declare(strict_types=1);

namespace App\Console\Commands\Viafirma;

use Illuminate\Console\Command;
use Symfony\Component\Console\Attribute\AsCommand;

/**
 * Wrapper seguro de `php artisan migrate --path=...` para el módulo Viafirma.
 *
 * Reglas (§10.bis del roadmap):
 *  1) El path DEBE estar dentro de `database/migrations/viafirma/`.
 *  2) Por defecto el comando se ejecuta en modo `--pretend`. Se requiere `--apply`
 *     para correr la migración real.
 *  3) En entornos no locales se exige también `--force` para evitar accidentes.
 *
 * Uso:
 *   php artisan viafirma:migrate 2026_05_14_100001_create_viafirma_certificate_requests_table.php
 *   php artisan viafirma:migrate <file> --apply
 *   php artisan viafirma:migrate <file> --apply --force
 *   php artisan viafirma:migrate <file> --rollback --apply
 */
#[AsCommand(name: 'viafirma:migrate')]
final class ViafirmaMigrateCommand extends Command
{
    protected $signature = 'viafirma:migrate
                            {file        : Nombre del archivo dentro de database/migrations/viafirma/}
                            {--apply     : Aplica la migración real (sin esto se ejecuta en modo --pretend)}
                            {--rollback  : Hace rollback en lugar de aplicar}
                            {--force     : Necesario para entornos no locales}';

    protected $description = 'Ejecuta UNA migración del módulo Viafirma de forma controlada y trazable.';

    private const VIAFIRMA_MIGRATIONS_DIR = 'database/migrations/viafirma';

    public function handle(): int
    {
        $file = (string) $this->argument('file');

        $relativePath = self::VIAFIRMA_MIGRATIONS_DIR . '/' . ltrim($file, '/');
        $absolutePath = base_path($relativePath);

        if (!str_starts_with(realpath($absolutePath) ?: '', realpath(base_path(self::VIAFIRMA_MIGRATIONS_DIR)) ?: '___')) {
            $this->error('❌ El archivo debe vivir en /' . self::VIAFIRMA_MIGRATIONS_DIR . "/. Recibido: '{$file}'");
            return self::FAILURE;
        }
        if (!is_file($absolutePath)) {
            $this->error("❌ No existe la migración: {$absolutePath}");
            return self::FAILURE;
        }

        $apply    = (bool) $this->option('apply');
        $rollback = (bool) $this->option('rollback');
        $force    = (bool) $this->option('force');

        if (!$apply) {
            $this->warn('⚠️  Modo dry-run (--pretend). Pasa --apply para ejecutar de verdad.');
        }
        if ($apply && !app()->environment('local') && !$force) {
            $this->error('❌ Estás fuera de "local". Debes añadir --force para confirmar.');
            return self::FAILURE;
        }

        $artisanCmd = $rollback ? 'migrate:rollback' : 'migrate';
        $params = [
            '--path' => '/' . $relativePath,
        ];
        if (!$apply) {
            $params['--pretend'] = true;
        }
        if ($apply) {
            $params['--force'] = true; // necesario para no-interactive en cualquier env
        }
        if ($rollback) {
            $params['--step'] = 1;
        }

        $this->info("➡️  artisan {$artisanCmd} " . http_build_query($params, '', ' '));
        $exit = $this->call($artisanCmd, $params);

        if ($exit === 0 && $apply) {
            $this->info("✅ Migración {$file} ejecutada (" . ($rollback ? 'rollback' : 'apply') . ").");
            $this->line("📝 Recuerda registrar la ejecución en CHANGELOG.md (política §10.bis).");
        }
        return $exit;
    }
}


