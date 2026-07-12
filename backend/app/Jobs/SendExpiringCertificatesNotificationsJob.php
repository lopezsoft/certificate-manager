<?php

namespace App\Jobs;

use App\Enums\CertificateRequestStatusEnum;
use App\Models\CertificateRequest;
use App\Notifications\CompanyExpiringCertificatesNotification;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Notification;
use Carbon\Carbon;
use Exception;

/**
 * Job para enviar notificaciones a empresas sobre certificados próximos a vencer
 *
 * Este Job se ejecuta diariamente y notifica a las empresas que tienen certificados
 * que vencerán en los próximos 30 días.
 *
 * Principios aplicados:
 * - Single Responsibility: Solo se encarga de procesar y enviar notificaciones a empresas
 * - Dependency Injection: Utiliza los servicios de Laravel de manera inyectada
 * - Error Handling: Manejo robusto de errores con logging detallado
 *
 * @package App\Jobs
 */
class SendExpiringCertificatesNotificationsJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $timeout = 300; // 5 minutos
    public $tries = 3;
    public $backoff = [60, 120, 300]; // Reintentos progresivos

    /**
     * Create a new job instance.
     */
    public function __construct()
    {
        $this->onQueue('notifications');
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
            Log::info('[CertificateExpiration] Iniciando proceso de notificaciones consolidadas de certificados próximos a vencer');

            $notificationDays = config('certificate.notification_days', 30);
            $expirationThreshold = now()->addDays($notificationDays);

            // Obtener certificados próximos a vencer
            $expiringCertificates = $this->getExpiringCertificates($expirationThreshold);

            if ($expiringCertificates->isEmpty()) {
                Log::info('[CertificateExpiration] No se encontraron certificados próximos a vencer');
                return;
            }

            Log::info('[CertificateExpiration] Certificados encontrados para notificar', [
                'count' => $expiringCertificates->count()
            ]);

            $successCount = 0;
            $failedCount = 0;
            $skippedCount = 0;

            // Agrupar certificados por empresa
            $certificatesByCompany = $expiringCertificates->groupBy('company_id');
            $totalCompanies = $certificatesByCompany->count();

            // Procesar cada empresa
            foreach ($certificatesByCompany as $companyId => $companysCertificates) {
                try {
                    // Obtener empresa
                    $company = $companysCertificates->first()->company;

                    if (!$company || !$company->email) {
                        Log::warning('[CertificateExpiration] Empresa sin email configurado', [
                            'company_id' => $companyId
                        ]);
                        $skippedCount++;
                        continue;
                    }

                    // Filtrar certificados no notificados hoy
                    $certificatesToNotify = $companysCertificates->filter(function ($cert) {
                        return !$this->wasNotifiedToday($cert);
                    });

                    if ($certificatesToNotify->isEmpty()) {
                        $skippedCount++;
                        Log::debug('[CertificateExpiration] Todos los certificados de empresa ya notificados hoy', [
                            'company_id' => $companyId,
                            'company' => $company->name
                        ]);
                        continue;
                    }

                    // Enviar notificación consolidada
                    $this->sendNotification($company, $certificatesToNotify);

                    // Marcar todos los certificados como notificados
                    foreach ($certificatesToNotify as $cert) {
                        $this->markAsNotified($cert);
                    }

                    $successCount++;

                    Log::info('[CertificateExpiration] Notificación consolidada enviada exitosamente', [
                        'company_id' => $company->id,
                        'company' => $company->name,
                        'email' => $company->email,
                        'certificates_count' => $certificatesToNotify->count()
                    ]);

                } catch (Exception $e) {
                    $failedCount++;
                    Log::error('[CertificateExpiration] Error al enviar notificación consolidada', [
                        'company_id' => $companyId,
                        'error' => $e->getMessage(),
                        'trace' => $e->getTraceAsString()
                    ]);
                }
            }

            $processingTime = microtime(true) - $startTime;

            // Log del resumen de ejecución
            Log::info('[CertificateExpiration] Proceso completado', [
                'total_certificates' => $expiringCertificates->count(),
                'total_companies' => $totalCompanies,
                'success' => $successCount,
                'failed' => $failedCount,
                'skipped' => $skippedCount,
                'processing_time' => round($processingTime, 2) . 's'
            ]);

            // Si hay muchos fallos, notificar al admin
            if ($failedCount > 5 || ($failedCount > 0 && $failedCount / $totalCompanies > 0.2)) {
                $this->notifyAdminAboutFailures($failedCount, $totalCompanies);
            }

        } catch (Exception $e) {
            Log::error('[CertificateExpiration] Error crítico en el proceso de notificaciones', [
                'error' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => $e->getTraceAsString()
            ]);

            throw $e;
        }
    }

    /**
     * Obtener certificados próximos a vencer Y vencidos recientes (sin renovar).
     *
     * Incluye tanto los que vencerán en el rango de antelación configurado como
     * los que ya vencieron, acotados a una antigüedad máxima (config
     * certificate.expired_report_max_age_days) para no generar ruido con
     * certificados vencidos hace meses/años que el cliente probablemente ya
     * abandonó. Excluye certificados cuyo cliente ya renovó (existe uno más
     * reciente PROCESSED para el mismo NIT/empresa).
     *
     * @param Carbon $expirationThreshold
     * @return \Illuminate\Database\Eloquent\Collection
     */
    private function getExpiringCertificates(Carbon $expirationThreshold)
    {
        $expiredMaxAgeDays = config('certificate.expired_report_max_age_days', 30);

        return CertificateRequest::with(['company'])
            ->whereNotNull('expiration_date')
            ->where('request_status', CertificateRequestStatusEnum::PROCESSED->value) // Solo certificados emitidos
            ->where('expiration_date', '<=', $expirationThreshold) // Dentro del rango de aviso
            ->where('expiration_date', '>=', now()->subDays($expiredMaxAgeDays)) // Vencidos recientes, no ruido histórico
            ->whereHas('company', function ($query) {
                $query->whereNotNull('email')
                      ->where('email', '!=', '');
            })
            ->whereNotExists(function ($sub) {
                $sub->select(\Illuminate\Support\Facades\DB::raw(1))
                    ->from('certificate_requests as renewed')
                    ->whereColumn('renewed.company_id', 'certificate_requests.company_id')
                    ->whereColumn('renewed.dni', 'certificate_requests.dni')
                    ->whereColumn('renewed.updated_at', '>', 'certificate_requests.updated_at')
                    ->where('renewed.request_status', CertificateRequestStatusEnum::PROCESSED->value);
            })
            ->orderBy('expiration_date', 'asc')
            ->get();
    }

    /**
     * Verificar si ya se notificó hoy este certificado
     *
     * @param CertificateRequest $certificate
     * @return bool
     */
    private function wasNotifiedToday(CertificateRequest $certificate): bool
    {
        $cacheKey = "cert_expiration_notified_{$certificate->id}_" . now()->format('Y-m-d');
        return Cache::has($cacheKey);
    }

    /**
     * Marcar certificado como notificado
     *
     * @param CertificateRequest $certificate
     * @return void
     */
    private function markAsNotified(CertificateRequest $certificate): void
    {
        $cacheKey = "cert_expiration_notified_{$certificate->id}_" . now()->format('Y-m-d');
        Cache::put($cacheKey, true, now()->addDay());
    }

    /**
     * Enviar notificación consolidada a la empresa
     *
     * @param \App\Models\Company $company
     * @param \Illuminate\Database\Eloquent\Collection $certificates
     * @return void
     */
    private function sendNotification($company, $certificates): void
    {
        Notification::route('mail', $company->email)
            ->notify(new CompanyExpiringCertificatesNotification($company, $certificates));
    }

    /**
     * Notificar al administrador sobre fallos masivos
     *
     * @param int $failedCount
     * @param int $totalCount
     * @return void
     */
    private function notifyAdminAboutFailures(int $failedCount, int $totalCount): void
    {
        try {
            $adminEmail = config('certificate.admin_email', config('mail.support_address'));

            if (!$adminEmail) {
                Log::warning('[CertificateExpiration] No se pudo notificar al admin: email no configurado');
                return;
            }

            $message = "Se detectaron múltiples fallos al enviar notificaciones de certificados próximos a vencer.\n\n";
            $message .= "Total procesados: {$totalCount}\n";
            $message .= "Fallos: {$failedCount}\n";
            $message .= "Tasa de error: " . round(($failedCount / $totalCount) * 100, 2) . "%\n\n";
            $message .= "Revise los logs del sistema para más detalles.";

            Notification::route('mail', $adminEmail)
                ->notify(new \App\Notifications\EmailSuppressedNotification($message));

            Log::warning('[CertificateExpiration] Notificación de fallos enviada al administrador', [
                'failed_count' => $failedCount,
                'total_count' => $totalCount,
                'admin_email' => $adminEmail
            ]);

        } catch (Exception $e) {
            Log::error('[CertificateExpiration] Error al notificar fallos al admin', [
                'error' => $e->getMessage()
            ]);
        }
    }

    /**
     * Handle a job failure.
     *
     * @param Exception $exception
     * @return void
     */
    public function failed(Exception $exception): void
    {
        Log::error('[CertificateExpiration] Job de notificaciones falló permanentemente', [
            'error' => $exception->getMessage(),
            'attempts' => $this->attempts()
        ]);

        // Notificar al administrador del fallo crítico
        try {
            $adminEmail = config('certificate.admin_email', config('mail.support_address'));

            if ($adminEmail) {
                $message = "ALERTA CRÍTICA: El Job de notificaciones de certificados próximos a vencer ha fallado permanentemente.\n\n";
                $message .= "Error: {$exception->getMessage()}\n";
                $message .= "Fecha: " . now()->format('Y-m-d H:i:s') . "\n\n";
                $message .= "Por favor, revise el sistema inmediatamente.";

                Notification::route('mail', $adminEmail)
                    ->notify(new \App\Notifications\EmailSuppressedNotification($message));
            }
        } catch (Exception $e) {
            Log::error('[CertificateExpiration] No se pudo notificar fallo crítico al admin', [
                'error' => $e->getMessage()
            ]);
        }
    }
}
