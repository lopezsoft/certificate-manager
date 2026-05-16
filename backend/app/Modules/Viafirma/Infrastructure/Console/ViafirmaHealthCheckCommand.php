<?php

declare(strict_types=1);

namespace App\Modules\Viafirma\Infrastructure\Console;

use App\Modules\Viafirma\Domain\Enums\InternalState;
use App\Modules\Viafirma\Infrastructure\CircuitBreaker\ViafirmaCircuitBreaker;
use App\Modules\Viafirma\Infrastructure\Persistence\Models\ViafirmaCertificateRequest;
use Illuminate\Console\Command;

/**
 * Health check de diagnóstico para el módulo Viafirma (V-502).
 *
 * Reporta métricas clave:
 *  - Total solicitudes por estado
 *  - Solicitudes en polling > 24h (alerta V-503)
 *  - Ratio de fallo
 *  - Estado del circuit breaker
 *  - Solicitudes huérfanas (stalled)
 *
 * Uso: php artisan viafirma:health-check
 */
final class ViafirmaHealthCheckCommand extends Command
{
    protected $signature   = 'viafirma:health-check';
    protected $description = 'Diagnóstico del estado del módulo Viafirma PKCS#10';

    public function handle(ViafirmaCircuitBreaker $circuitBreaker): int
    {
        $this->info('╔══════════════════════════════════════════════════════╗');
        $this->info('║         Viafirma PKCS#10 — Health Check             ║');
        $this->info('╚══════════════════════════════════════════════════════╝');
        $this->newLine();

        // 1. Solicitudes por estado
        $stateGroups = ViafirmaCertificateRequest::query()
            ->selectRaw("internal_state, COUNT(*) as total")
            ->groupBy('internal_state')
            ->pluck('total', 'internal_state')
            ->toArray();

        $this->table(
            ['Estado', 'Cantidad'],
            collect($stateGroups)->map(fn ($count, $state) => [$state, $count])->values()->toArray()
        );

        $total = array_sum($stateGroups);
        $this->info("Total solicitudes: {$total}");
        $this->newLine();

        // 2. Métricas de rendimiento
        $completed = $stateGroups[InternalState::COMPLETED->value] ?? 0;
        $failed    = ($stateGroups[InternalState::FAILED->value] ?? 0) + ($stateGroups[InternalState::EXPIRED->value] ?? 0);
        $failRatio = $total > 0 ? round(($failed / $total) * 100, 2) : 0;

        $this->info("✅ Completadas: {$completed}");
        $this->info("❌ Fallidas + Expiradas: {$failed}");

        if ($failRatio > 5) {
            $this->error("⚠️  Ratio de fallo: {$failRatio}% (umbral: 5%)");
        } else {
            $this->info("📊 Ratio de fallo: {$failRatio}%");
        }
        $this->newLine();

        // 3. Solicitudes en accreditation > 24h
        $stalledAccreditation = ViafirmaCertificateRequest::query()
            ->where('internal_state', InternalState::POLLING->value)
            ->where('remote_status', 'accreditation')
            ->where('submitted_at', '<', now()->subHours(24))
            ->count();

        if ($stalledAccreditation > 0) {
            $this->error("⚠️  Solicitudes en accreditation > 24h: {$stalledAccreditation}");
        } else {
            $this->info("✅ No hay solicitudes en accreditation > 24h");
        }

        // 4. Solicitudes huérfanas
        $orphaned = ViafirmaCertificateRequest::query()
            ->whereIn('internal_state', [InternalState::SUBMITTED->value, InternalState::POLLING->value])
            ->where(function ($q) {
                $q->where('next_poll_at', '<', now()->subMinutes(20))
                    ->orWhereNull('next_poll_at');
            })
            ->count();

        if ($orphaned > 0) {
            $this->warn("⚠️  Solicitudes huérfanas (stalled): {$orphaned}");
        } else {
            $this->info("✅ No hay solicitudes huérfanas");
        }
        $this->newLine();

        // 5. Circuit Breaker
        $cbOpen    = $circuitBreaker->isOpen();
        $cbFailures = $circuitBreaker->failureCount();

        if ($cbOpen) {
            $this->error("🔴 Circuit Breaker: OPEN ({$cbFailures} fallos acumulados)");
        } else {
            $this->info("🟢 Circuit Breaker: CLOSED ({$cbFailures} fallos acumulados)");
        }
        $this->newLine();

        // 6. Feature Flag
        $enabled = config('viafirma.feature_flag.enabled', true);
        $rollout = config('viafirma.feature_flag.rollout_percentage', 100);
        $this->info("🚀 Feature Flag: " . ($enabled ? "ACTIVO ({$rollout}%)" : "DESHABILITADO"));
        $this->newLine();

        // 7. Config summary
        $this->info('📋 Configuración:');
        $this->table(
            ['Parámetro', 'Valor'],
            [
                ['Base URL', config('viafirma.base_url') ? '✅ Configurada' : '❌ Falta'],
                ['Download URL', config('viafirma.download_url') ? '✅ Configurada' : '❌ Falta'],
                ['Client ID', config('viafirma.client_id') ? '✅ Configurado' : '❌ Falta'],
                ['RA Code', config('viafirma.ra_code') ? '✅ Configurado' : '❌ Falta'],
                ['KeyVault Driver', config('viafirma.crypto.key_vault_driver', 'encrypted_local')],
                ['Poll Max Attempts', (string) config('viafirma.polling.max_attempts')],
                ['SLA Hours', (string) config('viafirma.polling.expiration_hours')],
                ['CB Threshold', (string) config('viafirma.circuit_breaker.failure_threshold')],
            ]
        );

        return self::SUCCESS;
    }
}
