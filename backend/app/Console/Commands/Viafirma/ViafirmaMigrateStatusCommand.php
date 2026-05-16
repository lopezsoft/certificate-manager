<?php

declare(strict_types=1);

namespace App\Console\Commands\Viafirma;

use Illuminate\Console\Command;
use Symfony\Component\Console\Attribute\AsCommand;

/**
 * Lista las migraciones del módulo Viafirma y su estado en la tabla `migrations`.
 */
#[AsCommand(name: 'viafirma:migrate:status')]
final class ViafirmaMigrateStatusCommand extends Command
{
    protected $signature = 'viafirma:migrate:status';
    protected $description = 'Muestra el estado de las migraciones del módulo Viafirma.';

    public function handle(): int
    {
        $dir = base_path('database/migrations/viafirma');
        if (!is_dir($dir)) {
            $this->warn("⚠️  Directorio no existe aún: {$dir}");
            return self::SUCCESS;
        }

        $files = glob($dir . '/*.php') ?: [];
        if ($files === []) {
            $this->info('No hay migraciones registradas todavía en el módulo Viafirma.');
            return self::SUCCESS;
        }

        // Reusa el comando nativo apuntando a la carpeta del módulo.
        return $this->call('migrate:status', ['--path' => '/database/migrations/viafirma']);
    }
}

