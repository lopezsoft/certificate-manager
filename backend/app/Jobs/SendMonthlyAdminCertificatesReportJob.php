<?php

namespace App\Jobs;

use App\Models\CertificateRequest;
use App\Models\Company;
use App\Notifications\MonthlyAdminCertificatesReportNotification;
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
 * Job para enviar informe mensual consolidado al administrador
 * 
 * Este Job genera un reporte global de todos los certificados emitidos
 * durante el mes, agrupados por empresa, estado y vigencia.
 * 
 * @package App\Jobs
 */
class SendMonthlyAdminCertificatesReportJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $timeout = 600;
    public $tries = 3;
    public $backoff = [120, 300, 600];

    private Carbon $startDate;
    private Carbon $endDate;

    /**
     * Create a new job instance.
     *
     * @param Carbon|null $startDate
     * @param Carbon|null $endDate
     */
    public function __construct(?Carbon $startDate = null, ?Carbon $endDate = null)
    {
        if (!$startDate) {
            $this->startDate = now()->subMonth()->startOfMonth();
        } else {
            $this->startDate = $startDate->copy()->startOfDay();
        }
        
        if (!$endDate) {
            $this->endDate = now()->subMonth()->endOfMonth();
        } else {
            $this->endDate = $endDate->copy()->endOfDay();
        }
        
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
            $periodName = $this->startDate->locale('es')->isoFormat('MMMM YYYY');
            
            Log::info('[MonthlyAdminReport] Iniciando generación de informe mensual administrativo', [
                'period' => $periodName,
                'start_date' => $this->startDate->toDateString(),
                'end_date' => $this->endDate->toDateString()
            ]);

            $adminEmail = $this->getAdminEmail();

            if (!$adminEmail) {
                Log::error('[MonthlyAdminReport] No se pudo generar informe: email de administrador no configurado');
                return;
            }

            // Generar datos del reporte
            $reportData = $this->generateAdminReportData();

            if ($reportData['total_certificates'] === 0) {
                Log::info('[MonthlyAdminReport] No hay certificados emitidos en el periodo', [
                    'period' => $periodName
                ]);
                
                // Enviar reporte vacío
                $this->sendEmptyReport($adminEmail);
                return;
            }

            // Enviar reporte
            $this->sendReport($adminEmail, $reportData);

            $processingTime = microtime(true) - $startTime;

            Log::info('[MonthlyAdminReport] Informe mensual administrativo generado y enviado', [
                'admin_email' => $adminEmail,
                'period' => $periodName,
                'total_certificates' => $reportData['total_certificates'],
                'total_companies' => $reportData['total_companies'],
                'processing_time' => round($processingTime, 2) . 's'
            ]);

        } catch (Exception $e) {
            Log::error('[MonthlyAdminReport] Error crítico generando informe mensual administrativo', [
                'error' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => $e->getTraceAsString()
            ]);

            throw $e;
        }
    }

    /**
     * Generar datos del reporte administrativo
     *
     * @return array
     */
    private function generateAdminReportData(): array
    {
        // Obtener todos los certificados del periodo
        $certificates = CertificateRequest::whereBetween('created_at', [$this->startDate, $this->endDate])
            ->with(['company', 'identity', 'organization', 'city'])
            ->orderBy('created_at', 'desc')
            ->get();

        // Agrupar por empresa
        $byCompany = $certificates->groupBy('company_id')
            ->map(function ($companyCerts, $companyId) {
                $company = $companyCerts->first()->company;
                
                return [
                    'company_id' => $companyId,
                    'company_name' => $company->company_name ?? 'N/A',
                    'company_email' => $company->email ?? 'Sin email',
                    'count' => $companyCerts->count(),
                    'certificates' => $companyCerts->map(function ($cert) {
                        return $this->formatCertificateData($cert);
                    })
                ];
            })
            ->sortByDesc('count')
            ->values();

        // Agrupar por estado
        $byStatus = $certificates->groupBy('request_status')
            ->map(function ($items, $status) {
                return [
                    'status' => $status,
                    'count' => $items->count(),
                    'percentage' => 0 // Se calculará después
                ];
            });

        // Calcular porcentajes
        $total = $certificates->count();
        if ($total > 0) {
            $byStatus = $byStatus->map(function ($item) use ($total) {
                $item['percentage'] = round(($item['count'] / $total) * 100, 1);
                return $item;
            });
        }

        // Agrupar por vigencia
        $now = now();
        $activeCount = 0;
        $expiredCount = 0;
        $expiringCount = 0;
        $pendingCount = 0;

        foreach ($certificates as $cert) {
            if (!$cert->expiration_date) {
                $pendingCount++;
                continue;
            }

            $expirationDate = Carbon::parse($cert->expiration_date);
            
            if ($expirationDate < $now) {
                $expiredCount++;
            } elseif ($expirationDate <= $now->copy()->addDays(30)) {
                $expiringCount++;
            } else {
                $activeCount++;
            }
        }

        return [
            'period_name' => $this->startDate->locale('es')->isoFormat('MMMM YYYY'),
            'period_start' => $this->startDate->format('d/m/Y'),
            'period_end' => $this->endDate->format('d/m/Y'),
            'report_date' => now()->format('d/m/Y H:i'),
            'report_date_formatted' => now()->locale('es')->isoFormat('dddd, D [de] MMMM [de] YYYY [a las] HH:mm'),
            
            'total_certificates' => $certificates->count(),
            'total_companies' => $byCompany->count(),
            
            'by_company' => $byCompany,
            'by_status' => $byStatus,
            
            'by_validity' => [
                'active' => $activeCount,
                'expiring' => $expiringCount,
                'expired' => $expiredCount,
                'pending' => $pendingCount,
            ],
            
            'stats' => [
                'avg_per_company' => $byCompany->count() > 0 
                    ? round($certificates->count() / $byCompany->count(), 1) 
                    : 0,
                'active_percentage' => $certificates->count() > 0 
                    ? round(($activeCount / $certificates->count()) * 100, 1) 
                    : 0,
                'companies_with_active' => $byCompany->filter(function ($company) {
                    return $company['certificates']->where('is_expired', false)->count() > 0;
                })->count(),
                'companies_with_expired' => $byCompany->filter(function ($company) {
                    return $company['certificates']->where('is_expired', true)->count() > 0;
                })->count(),
            ],
            
            'top_companies' => $byCompany->take(10),
        ];
    }

    /**
     * Formatear datos de un certificado
     *
     * @param CertificateRequest $cert
     * @return array
     */
    private function formatCertificateData(CertificateRequest $cert): array
    {
        $expirationDate = $cert->expiration_date ? Carbon::parse($cert->expiration_date) : null;
        $daysRemaining = $expirationDate ? now()->diffInDays($expirationDate, false) : null;
        
        return [
            'id' => $cert->id,
            'company_name' => $cert->company_name,
            'dni' => $cert->dni,
            'dni_formatted' => $cert->dv 
                ? number_format($cert->dni, 0, '', '.') . "-{$cert->dv}"
                : number_format($cert->dni, 0, '', '.'),
            'request_status' => $cert->request_status,
            'expiration_date_formatted' => $expirationDate ? $expirationDate->format('d/m/Y') : 'Pendiente',
            'days_remaining' => $daysRemaining,
            'is_expired' => $expirationDate ? $expirationDate < now() : false,
            'is_expiring_soon' => $expirationDate ? ($expirationDate <= now()->addDays(30) && $expirationDate >= now()) : false,
            'created_at_formatted' => Carbon::parse($cert->created_at)->format('d/m/Y'),
        ];
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
            ->notify(new MonthlyAdminCertificatesReportNotification($reportData));
    }

    /**
     * Enviar reporte vacío
     *
     * @param string $adminEmail
     * @return void
     */
    private function sendEmptyReport(string $adminEmail): void
    {
        $reportData = [
            'period_name' => $this->startDate->locale('es')->isoFormat('MMMM YYYY'),
            'period_start' => $this->startDate->format('d/m/Y'),
            'period_end' => $this->endDate->format('d/m/Y'),
            'report_date_formatted' => now()->locale('es')->isoFormat('dddd, D [de] MMMM [de] YYYY'),
            'total_certificates' => 0,
            'is_empty' => true,
        ];

        Notification::route('mail', $adminEmail)
            ->notify(new MonthlyAdminCertificatesReportNotification($reportData));
    }

    /**
     * Obtener email del administrador
     *
     * @return string|null
     */
    private function getAdminEmail(): ?string
    {
        return config('certificate.admin_email', env('MAIL_SUPPORT_ADDRESS', config('mail.from.address')));
    }

    /**
     * Handle a job failure.
     *
     * @param Exception $exception
     * @return void
     */
    public function failed(Exception $exception): void
    {
        Log::error('[MonthlyAdminReport] Job de informe mensual administrativo falló', [
            'period' => $this->startDate->format('Y-m'),
            'error' => $exception->getMessage(),
            'attempts' => $this->attempts()
        ]);
    }
}
