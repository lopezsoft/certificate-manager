<?php

namespace App\Jobs;

use App\Models\CertificateRequest;
use App\Models\Company;
use App\Notifications\MonthlyCompanyCertificatesReportNotification;
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
 * Job para enviar informe mensual de certificados a cada empresa
 * 
 * Este Job se ejecuta el último día de cada mes y envía a cada empresa
 * un reporte detallado de todos los certificados emitidos durante el mes,
 * segmentados por estado y vigencia.
 * 
 * Principios aplicados:
 * - Single Responsibility: Solo se encarga del informe mensual por empresa
 * - Clean Code: Métodos claros y bien documentados
 * - Error Handling: Manejo robusto con logging detallado
 * 
 * @package App\Jobs
 */
class SendMonthlyCompanyCertificatesReportJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $timeout = 600; // 10 minutos
    public $tries = 3;
    public $backoff = [120, 300, 600];

    private ?int $companyId;
    private Carbon $startDate;
    private Carbon $endDate;

    /**
     * Create a new job instance.
     *
     * @param int|null $companyId Si es null, procesa todas las empresas
     * @param Carbon|null $startDate Inicio del periodo (por defecto: inicio del mes anterior)
     * @param Carbon|null $endDate Fin del periodo (por defecto: fin del mes anterior)
     */
    public function __construct(
        ?int $companyId = null,
        ?Carbon $startDate = null,
        ?Carbon $endDate = null
    ) {
        $this->companyId = $companyId;
        
        // Si no se especifica periodo, usar el mes que acaba de terminar
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
            
            Log::info('[MonthlyReport] Iniciando generación de informes mensuales de certificados', [
                'period' => $periodName,
                'start_date' => $this->startDate->toDateString(),
                'end_date' => $this->endDate->toDateString(),
                'specific_company_id' => $this->companyId
            ]);

            // Obtener empresas a procesar
            $companies = $this->getCompaniesToProcess();

            if ($companies->isEmpty()) {
                Log::info('[MonthlyReport] No hay empresas con certificados en el periodo', [
                    'period' => $periodName
                ]);
                return;
            }

            Log::info('[MonthlyReport] Empresas a procesar', [
                'count' => $companies->count(),
                'period' => $periodName
            ]);

            $successCount = 0;
            $failedCount = 0;
            $skippedCount = 0;

            // Procesar cada empresa
            foreach ($companies as $company) {
                try {
                    // Verificar que la empresa tenga email
                    if (!$company->email) {
                        Log::warning('[MonthlyReport] Empresa sin email configurado', [
                            'company_id' => $company->id,
                            'company_name' => $company->company_name
                        ]);
                        $skippedCount++;
                        continue;
                    }

                    // Obtener datos del reporte para esta empresa
                    $reportData = $this->generateCompanyReportData($company);

                    // Si no hay certificados en el periodo, omitir
                    if ($reportData['total_certificates'] === 0) {
                        Log::debug('[MonthlyReport] Empresa sin certificados en el periodo', [
                            'company_id' => $company->id,
                            'company_name' => $company->company_name,
                            'period' => $periodName
                        ]);
                        $skippedCount++;
                        continue;
                    }

                    // Enviar notificación
                    $this->sendCompanyReport($company, $reportData);
                    $successCount++;

                    Log::info('[MonthlyReport] Informe enviado exitosamente', [
                        'company_id' => $company->id,
                        'company_name' => $company->company_name,
                        'email' => $company->email,
                        'total_certificates' => $reportData['total_certificates'],
                        'period' => $periodName
                    ]);

                    // Pequeño delay para no saturar SMTP
                    usleep(200000); // 0.2 segundos

                } catch (Exception $e) {
                    $failedCount++;
                    Log::error('[MonthlyReport] Error al enviar informe a empresa', [
                        'company_id' => $company->id ?? null,
                        'company_name' => $company->company_name ?? 'N/A',
                        'error' => $e->getMessage(),
                        'trace' => $e->getTraceAsString()
                    ]);
                }
            }

            $processingTime = microtime(true) - $startTime;

            // Log del resumen de ejecución
            Log::info('[MonthlyReport] Proceso de informes mensuales completado', [
                'period' => $periodName,
                'total_companies' => $companies->count(),
                'success' => $successCount,
                'failed' => $failedCount,
                'skipped' => $skippedCount,
                'processing_time' => round($processingTime, 2) . 's'
            ]);

        } catch (Exception $e) {
            Log::error('[MonthlyReport] Error crítico en proceso de informes mensuales', [
                'error' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => $e->getTraceAsString()
            ]);

            throw $e;
        }
    }

    /**
     * Obtener empresas a procesar
     *
     * @return \Illuminate\Database\Eloquent\Collection
     */
    private function getCompaniesToProcess()
    {
        $query = Company::query()
            ->whereNotNull('email')
            ->where('email', '!=', '')
            ->whereHas('certificateRequests', function ($q) {
                $q->whereBetween('created_at', [$this->startDate, $this->endDate]);
            });

        // Si se especificó una empresa, filtrar
        if ($this->companyId) {
            $query->where('id', $this->companyId);
        }

        return $query->orderBy('company_name')->get();
    }

    /**
     * Generar datos del reporte para una empresa
     *
     * @param Company $company
     * @return array
     */
    private function generateCompanyReportData(Company $company): array
    {
        // Obtener todos los certificados del periodo
        $certificates = CertificateRequest::where('company_id', $company->id)
            ->whereBetween('created_at', [$this->startDate, $this->endDate])
            ->with(['identity', 'organization', 'city'])
            ->orderBy('created_at', 'desc')
            ->get();

        // Agrupar por estado
        $byStatus = $certificates->groupBy('request_status');
        
        // Agrupar por vigencia (activos/vencidos/próximos a vencer)
        $now = now();
        $activeCount = 0;
        $expiredCount = 0;
        $expiringCount = 0;
        $pendingCount = 0;

        $activeCertificates = collect();
        $expiredCertificates = collect();
        $expiringCertificates = collect();

        foreach ($certificates as $cert) {
            if (!$cert->expiration_date) {
                $pendingCount++;
                continue;
            }

            $expirationDate = Carbon::parse($cert->expiration_date);
            
            if ($expirationDate < $now) {
                $expiredCount++;
                $expiredCertificates->push($cert);
            } elseif ($expirationDate <= $now->copy()->addDays(30)) {
                $expiringCount++;
                $expiringCertificates->push($cert);
            } else {
                $activeCount++;
                $activeCertificates->push($cert);
            }
        }

        return [
            'company' => $company,
            'period_name' => $this->startDate->locale('es')->isoFormat('MMMM YYYY'),
            'period_start' => $this->startDate->format('d/m/Y'),
            'period_end' => $this->endDate->format('d/m/Y'),
            'total_certificates' => $certificates->count(),
            
            // Por estado
            'by_status' => $byStatus->map(function ($items, $status) {
                return [
                    'status' => $status,
                    'count' => $items->count(),
                    'certificates' => $items->map(function ($cert) {
                        return $this->formatCertificateData($cert);
                    })
                ];
            }),
            
            // Por vigencia
            'by_validity' => [
                'active' => [
                    'count' => $activeCount,
                    'certificates' => $activeCertificates->map(function ($cert) {
                        return $this->formatCertificateData($cert);
                    })
                ],
                'expiring' => [
                    'count' => $expiringCount,
                    'certificates' => $expiringCertificates->map(function ($cert) {
                        return $this->formatCertificateData($cert);
                    })
                ],
                'expired' => [
                    'count' => $expiredCount,
                    'certificates' => $expiredCertificates->map(function ($cert) {
                        return $this->formatCertificateData($cert);
                    })
                ],
                'pending' => [
                    'count' => $pendingCount
                ]
            ],
            
            // Estadísticas
            'stats' => [
                'active_count' => $activeCount,
                'expiring_count' => $expiringCount,
                'expired_count' => $expiredCount,
                'pending_count' => $pendingCount,
                'active_percentage' => $certificates->count() > 0 
                    ? round(($activeCount / $certificates->count()) * 100, 1) 
                    : 0,
            ],
            
            // Certificados completos
            'all_certificates' => $certificates->map(function ($cert) {
                return $this->formatCertificateData($cert);
            })
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
            'dv' => $cert->dv,
            'dni_formatted' => $cert->dv 
                ? number_format($cert->dni, 0, '', '.') . "-{$cert->dv}"
                : number_format($cert->dni, 0, '', '.'),
            'legal_representative' => $cert->legal_representative,
            'request_status' => $cert->request_status,
            'expiration_date' => $cert->expiration_date,
            'expiration_date_formatted' => $expirationDate ? $expirationDate->format('d/m/Y') : 'Pendiente',
            'days_remaining' => $daysRemaining,
            'is_expired' => $expirationDate ? $expirationDate < now() : false,
            'is_expiring_soon' => $expirationDate ? ($expirationDate <= now()->addDays(30) && $expirationDate >= now()) : false,
            'created_at' => $cert->created_at,
            'created_at_formatted' => Carbon::parse($cert->created_at)->format('d/m/Y H:i'),
            'city' => $cert->city->city_name ?? 'N/A',
            'phone' => $cert->phone,
            'address' => $cert->address,
        ];
    }

    /**
     * Enviar informe a la empresa
     *
     * @param Company $company
     * @param array $reportData
     * @return void
     */
    private function sendCompanyReport(Company $company, array $reportData): void
    {
        Notification::route('mail', $company->email)
            ->notify(new MonthlyCompanyCertificatesReportNotification($reportData));
    }

    /**
     * Handle a job failure.
     *
     * @param Exception $exception
     * @return void
     */
    public function failed(Exception $exception): void
    {
        Log::error('[MonthlyReport] Job de informe mensual falló permanentemente', [
            'company_id' => $this->companyId,
            'period' => $this->startDate->format('Y-m'),
            'error' => $exception->getMessage(),
            'attempts' => $this->attempts()
        ]);
    }
}
