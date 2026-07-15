<?php

namespace App\Jobs;

use App\Enums\CertificateRequestStatusEnum;
use App\Models\CertificateRequest;
use App\Notifications\AdminExpiringCertificatesReportNotification;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;
use Carbon\Carbon;
use Exception;

/**
 * Job para enviar reporte consolidado de certificados próximos a vencer al administrador
 * 
 * Este Job genera un reporte detallado de TODAS las empresas con certificados próximos
 * a vencer, segmentados por nivel de urgencia, para que el administrador del sistema
 * tenga trazabilidad completa y pueda gestionar renovaciones proactivamente.
 * 
 * Principios aplicados:
 * - Single Responsibility: Solo se encarga de generar y enviar el reporte administrativo
 * - Open/Closed: Diseñado para ser extensible sin modificar el código base
 * - Clean Code: Métodos pequeños y con nombres descriptivos
 * 
 * @package App\Jobs
 */
class SendAdminExpiringCertificatesReportJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $timeout = 300;
    public $tries = 3;
    public $backoff = [60, 120, 300];

    private bool $isWeeklyReport;

    /**
     * Create a new job instance.
     *
     * @param bool $isWeeklyReport
     */
    public function __construct(bool $isWeeklyReport = false)
    {
        $this->isWeeklyReport = $isWeeklyReport;
        $this->onQueue('reports');
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle(): void
    {
        $startTime = microtime(true);
        
        try {
            $reportType = $this->isWeeklyReport ? 'semanal' : 'diario';
            
            Log::info("[CertificateExpiration] Iniciando generación de reporte {$reportType} para administrador");

            $adminEmail = $this->getAdminEmail();

            if (!$adminEmail) {
                Log::error('[CertificateExpiration] No se pudo generar reporte: email de administrador no configurado');
                return;
            }

            // Recopilar datos del reporte
            $reportData = $this->gatherReportData();

            // Validar si hay datos para reportar
            if ($this->isReportEmpty($reportData)) {
                Log::info("[CertificateExpiration] No hay certificados próximos a vencer para el reporte {$reportType}");
                
                // Opcional: Enviar reporte "vacío" solo si es semanal
                if ($this->isWeeklyReport) {
                    $this->sendEmptyReport($adminEmail);
                }
                
                return;
            }

            // Enviar reporte
            $this->sendReport($adminEmail, $reportData);

            $processingTime = microtime(true) - $startTime;

            Log::info("[CertificateExpiration] Reporte {$reportType} generado y enviado exitosamente", [
                'admin_email' => $adminEmail,
                'total_certificates' => $reportData['total_count'],
                'critical' => $reportData['critical_count'],
                'high' => $reportData['high_count'],
                'medium' => $reportData['medium_count'],
                'expired' => $reportData['expired_count'],
                'processing_time' => round($processingTime, 2) . 's'
            ]);

        } catch (Exception $e) {
            Log::error('[CertificateExpiration] Error crítico generando reporte administrativo', [
                'error' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => $e->getTraceAsString()
            ]);

            throw $e;
        }
    }

    /**
     * Recopilar datos para el reporte
     *
     * @return array
     */
    private function gatherReportData(): array
    {
        // Certificados vencidos
        $expiredCertificates = $this->getCertificatesByRange('expired');
        
        // Certificados críticos (1-7 días)
        $criticalCertificates = $this->getCertificatesByRange('critical');
        
        // Certificados de alta prioridad (8-15 días)
        $highPriorityCertificates = $this->getCertificatesByRange('high');
        
        // Certificados de media prioridad (16-30 días)
        $mediumPriorityCertificates = $this->getCertificatesByRange('medium');

        // Estadísticas generales
        $totalCertificates = CertificateRequest::whereNotNull('expiration_date')->count();
        $activeCertificates = CertificateRequest::whereNotNull('expiration_date')
            ->where('expiration_date', '>', now())
            ->count();

        return [
            'report_type' => $this->isWeeklyReport ? 'weekly' : 'daily',
            'report_date' => now()->format('Y-m-d H:i:s'),
            'report_date_formatted' => now()->locale('es')->isoFormat('dddd, D [de] MMMM [de] YYYY [a las] HH:mm'),
            
            // Certificados por categoría
            'expired' => $expiredCertificates,
            'critical' => $criticalCertificates,
            'high_priority' => $highPriorityCertificates,
            'medium_priority' => $mediumPriorityCertificates,
            
            // Contadores
            'expired_count' => $expiredCertificates->count(),
            'critical_count' => $criticalCertificates->count(),
            'high_count' => $highPriorityCertificates->count(),
            'medium_count' => $mediumPriorityCertificates->count(),
            'total_count' => $expiredCertificates->count() + $criticalCertificates->count() + 
                            $highPriorityCertificates->count() + $mediumPriorityCertificates->count(),
            
            // Estadísticas generales
            'stats' => [
                'total_certificates' => $totalCertificates,
                'active_certificates' => $activeCertificates,
                'companies_affected' => $this->getAffectedCompaniesCount(),
            ],
            
            // Métricas adicionales
            'metrics' => $this->calculateMetrics(),
        ];
    }

    /**
     * Obtener certificados por rango de vencimiento
     *
     * @param string $range
     * @return \Illuminate\Database\Eloquent\Collection
     */
    private function getCertificatesByRange(string $range)
    {
        $query = CertificateRequest::with(['identity', 'city'])
            ->whereNotNull('expiration_date');

        switch ($range) {
            case 'expired':
                // Solo vencidos recientes (máximo N días) — más antiguo que eso es ruido,
                // el operador ya tuvo tiempo de gestionarlo o el cliente ya se fue.
                $maxAgeDays = config('certificate.expired_report_max_age_days', 30);
                $query->where('expiration_date', '<', now())
                      ->where('expiration_date', '>=', now()->subDays($maxAgeDays))
                      ->whereNotExists(function ($sub) {
                          $sub->select(\Illuminate\Support\Facades\DB::raw(1))
                              ->from('certificate_requests as renewed')
                              ->whereColumn('renewed.company_id', 'certificate_requests.company_id')
                              ->whereColumn('renewed.dni', 'certificate_requests.dni')
                              ->whereColumn('renewed.updated_at', '>', 'certificate_requests.updated_at')
                              ->where('renewed.request_status', CertificateRequestStatusEnum::PROCESSED->value);
                      });
                break;

            case 'critical':
                $query->where('expiration_date', '>=', now())
                      ->where('expiration_date', '<=', now()->addDays(7));
                break;

            case 'high':
                $query->where('expiration_date', '>', now()->addDays(7))
                      ->where('expiration_date', '<=', now()->addDays(15));
                break;

            case 'medium':
                $query->where('expiration_date', '>', now()->addDays(15))
                      ->where('expiration_date', '<=', now()->addDays(30));
                break;
        }

        return $query->orderBy('expiration_date', 'asc')
                    ->get()
                    ->map(function ($cert) {
                        $company = \Illuminate\Support\Facades\DB::table('companies')
                            ->where('id', $cert->company_id)
                            ->first();
                        return [
                            'id' => $cert->id,
                            'company_name' => $cert->company_name,
                            'dni' => $cert->dni,
                            'dv' => $cert->dv,
                            'email' => $company?->email ?? 'Sin email',
                            'phone' => $cert->phone,
                            'expiration_date' => $cert->expiration_date,
                            'expiration_date_formatted' => Carbon::parse($cert->expiration_date)->format('d/m/Y'),
                            'days_remaining' => now()->diffInDays(Carbon::parse($cert->expiration_date), false),
                            'city' => $cert->city->city_name ?? 'N/A',
                            'request_status' => $cert->request_status,
                            'legal_representative' => $cert->legal_representative,
                            'created_at' => $cert->created_at,
                        ];
                    });
    }

    /**
     * Obtener cantidad de empresas afectadas
     *
     * @return int
     */
    private function getAffectedCompaniesCount(): int
    {
        return CertificateRequest::whereNotNull('expiration_date')
            ->where('expiration_date', '<=', now()->addDays(30))
            ->distinct('company_id')
            ->count('company_id');
    }

    /**
     * Calcular métricas adicionales
     *
     * @return array
     */
    private function calculateMetrics(): array
    {
        $expiringNext7Days = CertificateRequest::whereNotNull('expiration_date')
            ->where('expiration_date', '>=', now())
            ->where('expiration_date', '<=', now()->addDays(7))
            ->count();

        $expiringNext30Days = CertificateRequest::whereNotNull('expiration_date')
            ->where('expiration_date', '>=', now())
            ->where('expiration_date', '<=', now()->addDays(30))
            ->count();

        return [
            'expiring_next_7_days' => $expiringNext7Days,
            'expiring_next_30_days' => $expiringNext30Days,
            'urgency_rate' => $expiringNext30Days > 0 
                ? round(($expiringNext7Days / $expiringNext30Days) * 100, 2) 
                : 0,
        ];
    }

    /**
     * Verificar si el reporte está vacío
     *
     * @param array $reportData
     * @return bool
     */
    private function isReportEmpty(array $reportData): bool
    {
        return $reportData['total_count'] === 0;
    }

    /**
     * Enviar reporte al administrador
     *
     * @param string $adminEmail
     * @param array $reportData
     * @return void
     */
    private function sendReport(string $adminEmail, array $reportData): void
    {
        Notification::route('mail', $adminEmail)
            ->notify(new AdminExpiringCertificatesReportNotification($reportData));
    }

    /**
     * Enviar reporte vacío (sin certificados próximos a vencer)
     *
     * @param string $adminEmail
     * @return void
     */
    private function sendEmptyReport(string $adminEmail): void
    {
        $reportData = [
            'report_type' => 'weekly',
            'report_date' => now()->format('Y-m-d H:i:s'),
            'report_date_formatted' => now()->locale('es')->isoFormat('dddd, D [de] MMMM [de] YYYY'),
            'total_count' => 0,
            'is_empty' => true,
        ];

        Notification::route('mail', $adminEmail)
            ->notify(new AdminExpiringCertificatesReportNotification($reportData));
    }

    /**
     * Obtener email del administrador
     *
     * @return string|null
     */
    private function getAdminEmail(): ?string
    {
        // Prioridad 1: Configuración específica de certificados
        $email = config('certificate.admin_email');
        
        if ($email) {
            return $email;
        }

        // Prioridad 2: Email de soporte general
        $email = config('mail.support_address', env('MAIL_SUPPORT_ADDRESS'));
        
        if ($email) {
            return $email;
        }

        // Prioridad 3: Email FROM por defecto
        return config('mail.from.address');
    }

    /**
     * Handle a job failure.
     *
     * @param Exception $exception
     * @return void
     */
    public function failed(Exception $exception): void
    {
        $reportType = $this->isWeeklyReport ? 'semanal' : 'diario';
        
        Log::error("[CertificateExpiration] Job de reporte {$reportType} falló permanentemente", [
            'error' => $exception->getMessage(),
            'attempts' => $this->attempts()
        ]);
    }
}
