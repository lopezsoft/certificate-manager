<?php

namespace App\Jobs;

use App\Models\CertificateRequest;
use App\Notifications\CertificateExpiringNotification;
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
            Log::info('[CertificateExpiration] Iniciando proceso de notificaciones de certificados próximos a vencer');

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

            // Procesar cada certificado
            foreach ($expiringCertificates as $certificate) {
                try {
                    // Verificar si ya se notificó hoy
                    if ($this->wasNotifiedToday($certificate)) {
                        $skippedCount++;
                        Log::debug('[CertificateExpiration] Certificado ya notificado hoy', [
                            'certificate_id' => $certificate->id,
                            'company' => $certificate->company_name
                        ]);
                        continue;
                    }

                    // Validar que la empresa tenga email
                    if (!$certificate->company || !$certificate->company->email) {
                        Log::warning('[CertificateExpiration] Empresa sin email configurado', [
                            'certificate_id' => $certificate->id,
                            'company_id' => $certificate->company_id
                        ]);
                        $skippedCount++;
                        continue;
                    }

                    // Enviar notificación
                    $this->sendNotification($certificate);
                    
                    // Marcar como notificado
                    $this->markAsNotified($certificate);
                    
                    $successCount++;

                    Log::info('[CertificateExpiration] Notificación enviada exitosamente', [
                        'certificate_id' => $certificate->id,
                        'company' => $certificate->company_name,
                        'email' => $certificate->company->email,
                        'expiration_date' => $certificate->expiration_date,
                        'days_remaining' => $this->getDaysRemaining($certificate)
                    ]);

                    // Pequeño delay para no saturar el servidor SMTP
                    usleep(100000); // 0.1 segundos

                } catch (Exception $e) {
                    $failedCount++;
                    Log::error('[CertificateExpiration] Error al enviar notificación individual', [
                        'certificate_id' => $certificate->id,
                        'company' => $certificate->company_name ?? 'N/A',
                        'error' => $e->getMessage(),
                        'trace' => $e->getTraceAsString()
                    ]);
                }
            }

            $processingTime = microtime(true) - $startTime;

            // Log del resumen de ejecución
            Log::info('[CertificateExpiration] Proceso completado', [
                'total_found' => $expiringCertificates->count(),
                'success' => $successCount,
                'failed' => $failedCount,
                'skipped' => $skippedCount,
                'processing_time' => round($processingTime, 2) . 's'
            ]);

            // Si hay muchos fallos, notificar al admin
            if ($failedCount > 5 || ($failedCount > 0 && $failedCount / $expiringCertificates->count() > 0.2)) {
                $this->notifyAdminAboutFailures($failedCount, $expiringCertificates->count());
            }

        } catch (Exception $e) {
            Log::error('[CertificateExpiration] Error crítico en el proceso de notificaciones', [
                'error' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => $e->getTraceAsString()
            ]);

            throw $e; // Re-lanzar para que se registre el fallo del Job
        }
    }

    /**
     * Obtener certificados próximos a vencer
     *
     * @param Carbon $expirationThreshold
     * @return \Illuminate\Database\Eloquent\Collection
     */
    private function getExpiringCertificates(Carbon $expirationThreshold)
    {
        return CertificateRequest::with(['company'])
            ->whereNotNull('expiration_date')
            ->where('request_status', 'PROCESSED') // Solo certificados emitidos
            ->where('expiration_date', '>', now()) // No vencidos aún
            ->where('expiration_date', '<=', $expirationThreshold) // Dentro del rango
            ->whereHas('company', function ($query) {
                $query->whereNotNull('email')
                      ->where('email', '!=', '');
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
     * Enviar notificación a la empresa
     *
     * @param CertificateRequest $certificate
     * @return void
     */
    private function sendNotification(CertificateRequest $certificate): void
    {
        $daysRemaining = $this->getDaysRemaining($certificate);
        $urgencyLevel = $this->getUrgencyLevel($daysRemaining);

        Notification::route('mail', $certificate->company->email)
            ->notify(new CertificateExpiringNotification(
                $certificate,
                $daysRemaining,
                $urgencyLevel
            ));
    }

    /**
     * Obtener días restantes hasta el vencimiento
     *
     * @param CertificateRequest $certificate
     * @return int
     */
    private function getDaysRemaining(CertificateRequest $certificate): int
    {
        return now()->diffInDays(Carbon::parse($certificate->expiration_date), false);
    }

    /**
     * Determinar nivel de urgencia
     *
     * @param int $daysRemaining
     * @return string
     */
    private function getUrgencyLevel(int $daysRemaining): string
    {
        if ($daysRemaining <= 7) {
            return 'critical';
        } elseif ($daysRemaining <= 15) {
            return 'high';
        } elseif ($daysRemaining <= 30) {
            return 'medium';
        }
        return 'low';
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
