<?php

declare(strict_types=1);

namespace App\Console\Commands\Viafirma;

use App\Jobs\Certificate\AutoIssueViafirmaJob;
use App\Models\CertificateRequest;
use App\Services\Certificate\CertificateIssuanceOrchestrator;
use Illuminate\Console\Command;
use Throwable;

/**
 * Ejecuta el mismo flujo exacto de AutoIssueViafirmaJob de forma
 * sincrónica en consola para poder depurar bloqueos paso a paso.
 *
 * NO duplica lógica — llama directamente al mismo job y orquestador.
 *
 * Uso:
 *   php artisan viafirma:debug-issue {id}
 */
class DebugViafirmaIssueCommand extends Command
{
    protected $signature   = 'viafirma:debug-issue {id : ID de la CertificateRequest}';
    protected $description = 'Ejecuta AutoIssueViafirmaJob en consola para depurar bloqueos';

    public function handle(CertificateIssuanceOrchestrator $orchestrator): int
    {
        $crId = (int) $this->argument('id');

        $this->line('');
        $this->info("══════════════════════════════════════════════════");
        $this->info("  VIAFIRMA DEBUG — CertificateRequest #{$crId}");
        $this->info("══════════════════════════════════════════════════");

        // Verificar que existe antes de llamar el job
        $cr = CertificateRequest::query()
            ->find($crId);

        if ($cr === null) {
            $this->error("  CertificateRequest #{$crId} no encontrada.");
            return 1;
        }

        $this->line("  company        : {$cr->company_name}");
        $this->line("  status         : {$cr->request_status}");
        $this->line("  type_org_id    : {$cr->type_organization_id}  (1=PJ, 2=PN)");
        $this->line("  legal_rep_email: " . ($cr->legal_rep_email ?? '(vacío)'));
        $this->line('');
        $this->line("  Config activa:");
        $this->line("    VIAFIRMA_RA_URL    : " . (config('viafirma.base_url') ?: '(vacío)'));
        $this->line("    VIAFIRMA_RA_CODE   : " . (config('viafirma.ra_code')  ?: '(vacío)'));
        $this->line("    VIAFIRMA_COD_PROFILE: " . (config('viafirma.cod_profile') ? substr(config('viafirma.cod_profile'), 0, 20).'…' : '(vacío)'));
        $this->line("    VIAFIRMA_PKCS10_ENABLED: " . (config('viafirma.feature_flag.enabled') ? 'true' : 'false'));
        $this->line('');
        $this->warn("  Ejecutando AutoIssueViafirmaJob->handle() ahora...");
        $this->warn("  (Observa storage/logs/laravel.log en paralelo)");
        $this->line('');

        $t = microtime(true);

        try {
            // Llamar EXACTAMENTE igual que el job real
            $job = new AutoIssueViafirmaJob($crId);
            $job->handle($orchestrator);

            $ms = round((microtime(true) - $t) * 1000);
            $this->line('');
            $this->info("  ✓ COMPLETADO en {$ms}ms");

        } catch (Throwable $e) {
            $ms = round((microtime(true) - $t) * 1000);
            $this->line('');
            $this->error("  ✗ FALLÓ en {$ms}ms");
            $this->error("  Clase   : " . get_class($e));
            $this->error("  Mensaje : " . $e->getMessage());
            $this->error("  Archivo : " . $e->getFile() . ':' . $e->getLine());
            $this->line('');
            $this->line('  Stack trace (últimas 5 líneas):');
            $frames = array_slice(explode("\n", $e->getTraceAsString()), 0, 5);
            foreach ($frames as $frame) {
                $this->line('    ' . $frame);
            }
            return 1;
        }

        $this->line('');
        return 0;
    }
}
