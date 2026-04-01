<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Notificación de reporte administrativo de certificados próximos a vencer
 * 
 * Esta notificación envía un reporte consolidado al administrador del sistema
 * con información detallada de todas las empresas que tienen certificados
 * próximos a vencer, segmentados por nivel de urgencia.
 * 
 * @package App\Notifications
 */
class AdminExpiringCertificatesReportNotification extends Notification implements ShouldQueue
{
    use Queueable;

    private array $reportData;

    /**
     * Create a new notification instance.
     *
     * @param array $reportData
     */
    public function __construct(array $reportData)
    {
        $this->reportData = $reportData;
        $this->onQueue('reports');
    }

    /**
     * Get the notification's delivery channels.
     *
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    /**
     * Get the mail representation of the notification.
     */
    public function toMail(object $notifiable): MailMessage
    {
        // Verificar si es reporte vacío
        if (isset($this->reportData['is_empty']) && $this->reportData['is_empty']) {
            return $this->buildEmptyReport();
        }

        return $this->buildFullReport();
    }

    /**
     * Construir reporte completo
     *
     * @return MailMessage
     */
    private function buildFullReport(): MailMessage
    {
        $reportType = $this->reportData['report_type'] === 'weekly' ? 'Semanal' : 'Diario';
        $subject = "📊 Reporte {$reportType} - Certificados Próximos a Vencer";

        $mail = (new MailMessage)
            ->subject($subject)
            ->greeting("Reporte de Certificados - {$this->reportData['report_date_formatted']}")
            ->line("A continuación se presenta el reporte consolidado de certificados próximos a vencer:")
            ->line('')
            ->line('---')
            ->line('')
            ->line("## 📊 RESUMEN EJECUTIVO")
            ->line('')
            ->line("**Total de certificados en riesgo:** {$this->reportData['total_count']}")
            ->line("**Empresas afectadas:** {$this->reportData['stats']['companies_affected']}")
            ->line('')
            ->line("### Distribución por Urgencia:")
            ->line("🚨 **Vencidos:** {$this->reportData['expired_count']}")
            ->line("🔴 **Críticos (1-7 días):** {$this->reportData['critical_count']}")
            ->line("🟠 **Alta Prioridad (8-15 días):** {$this->reportData['high_count']}")
            ->line("🟡 **Media Prioridad (16-30 días):** {$this->reportData['medium_count']}")
            ->line('')
            ->line('---')
            ->line('');

        // Certificados vencidos
        if ($this->reportData['expired_count'] > 0) {
            $mail->line("## 🚨 CERTIFICADOS VENCIDOS ({$this->reportData['expired_count']})")
                ->line('')
                ->line("**ACCIÓN INMEDIATA REQUERIDA**")
                ->line('');

            foreach ($this->reportData['expired']->take(10) as $cert) {
                $daysExpired = abs($cert['days_remaining']);
                $mail->line("- **{$cert['company_name']}** (NIT: {$cert['dni']})")
                    ->line("  ❌ Vencido hace {$daysExpired} días | 📅 {$cert['expiration_date_formatted']}")
                    ->line("  📧 {$cert['email']} | 📱 {$cert['phone']}")
                    ->line('');
            }

            if ($this->reportData['expired_count'] > 10) {
                $remaining = $this->reportData['expired_count'] - 10;
                $mail->line("*... y {$remaining} certificados vencidos más*")
                    ->line('');
            }
            
            $mail->line('---')->line('');
        }

        // Certificados críticos (1-7 días)
        if ($this->reportData['critical_count'] > 0) {
            $mail->line("## 🔴 CERTIFICADOS CRÍTICOS - 1 a 7 días ({$this->reportData['critical_count']})")
                ->line('')
                ->line("**URGENTE: Contactar inmediatamente para renovación**")
                ->line('');

            foreach ($this->reportData['critical']->take(15) as $cert) {
                $mail->line("- **{$cert['company_name']}** (NIT: {$cert['dni']})")
                    ->line("  ⏰ Vence en {$cert['days_remaining']} días | 📅 {$cert['expiration_date_formatted']}")
                    ->line("  📧 {$cert['email']} | 📱 {$cert['phone']}")
                    ->line("  👤 Rep. Legal: {$cert['legal_representative']}")
                    ->line('');
            }

            if ($this->reportData['critical_count'] > 15) {
                $remaining = $this->reportData['critical_count'] - 15;
                $mail->line("*... y {$remaining} certificados críticos más*")
                    ->line('');
            }

            $mail->line('---')->line('');
        }

        // Certificados de alta prioridad (8-15 días)
        if ($this->reportData['high_count'] > 0) {
            $mail->line("## 🟠 ALTA PRIORIDAD - 8 a 15 días ({$this->reportData['high_count']})")
                ->line('')
                ->line("**Programar contacto para renovación en los próximos días**")
                ->line('');

            foreach ($this->reportData['high_priority']->take(10) as $cert) {
                $mail->line("- **{$cert['company_name']}** (NIT: {$cert['dni']})")
                    ->line("  ⏰ Vence en {$cert['days_remaining']} días | 📅 {$cert['expiration_date_formatted']}")
                    ->line("  📧 {$cert['email']}")
                    ->line('');
            }

            if ($this->reportData['high_count'] > 10) {
                $remaining = $this->reportData['high_count'] - 10;
                $mail->line("*... y {$remaining} certificados de alta prioridad más*")
                    ->line('');
            }

            $mail->line('---')->line('');
        }

        // Certificados de media prioridad (16-30 días)
        if ($this->reportData['medium_count'] > 0) {
            $mail->line("## 🟡 MEDIA PRIORIDAD - 16 a 30 días ({$this->reportData['medium_count']})")
                ->line('')
                ->line("**Monitorear para contacto oportuno**")
                ->line('');

            foreach ($this->reportData['medium_priority']->take(8) as $cert) {
                $mail->line("- **{$cert['company_name']}** (NIT: {$cert['dni']}) - Vence en {$cert['days_remaining']} días");
            }

            if ($this->reportData['medium_count'] > 8) {
                $remaining = $this->reportData['medium_count'] - 8;
                $mail->line('')
                    ->line("*... y {$remaining} certificados de media prioridad más*");
            }

            $mail->line('')->line('---')->line('');
        }

        // Estadísticas adicionales
        $mail->line("## 📈 ESTADÍSTICAS GENERALES")
            ->line('')
            ->line("**Total de certificados en sistema:** {$this->reportData['stats']['total_certificates']}")
            ->line("**Certificados activos:** {$this->reportData['stats']['active_certificates']}")
            ->line("**Tasa de urgencia:** {$this->reportData['metrics']['urgency_rate']}%")
            ->line('')
            ->line('---')
            ->line('')
            ->line("## 🎯 RECOMENDACIONES")
            ->line('');

        // Agregar recomendaciones según el estado
        if ($this->reportData['expired_count'] > 0) {
            $mail->line("- ❗ **CRÍTICO:** {$this->reportData['expired_count']} certificados ya vencidos requieren atención inmediata");
        }
        if ($this->reportData['critical_count'] > 0) {
            $mail->line("- 🔴 Contactar urgentemente a {$this->reportData['critical_count']} empresas con certificados críticos");
        }
        if ($this->reportData['high_count'] > 0) {
            $mail->line("- 🟠 Programar llamadas de renovación para {$this->reportData['high_count']} empresas de alta prioridad");
        }
        if ($this->reportData['medium_count'] > 0) {
            $mail->line("- 🟡 Preparar campaña de recordatorio para {$this->reportData['medium_count']} empresas de media prioridad");
        }

        $mail->line('')
            ->line('---')
            ->line('')
            ->action('Acceder al Sistema de Gestión', config('app.frontend_url', config('app.url')))
            ->line('')
            ->line("*Este es un reporte automático generado por el sistema de gestión de certificados.*")
            ->line("*Para más detalles, acceda al panel administrativo.*")
            ->salutation("Sistema de Gestión de Certificados\n" . config('app.name'));

        return $mail;
    }

    /**
     * Construir reporte vacío
     *
     * @return MailMessage
     */
    private function buildEmptyReport(): MailMessage
    {
        return (new MailMessage)
            ->subject("✅ Reporte Semanal - Sin Certificados Próximos a Vencer")
            ->greeting("Reporte de Certificados - {$this->reportData['report_date_formatted']}")
            ->line("✅ **Excelentes noticias:**")
            ->line('')
            ->line("No hay certificados próximos a vencer en los próximos 30 días.")
            ->line('')
            ->line("Todos los certificados activos se encuentran vigentes y no requieren acción inmediata.")
            ->line('')
            ->action('Ver Panel de Certificados', config('app.frontend_url', config('app.url')))
            ->line('')
            ->salutation("Sistema de Gestión de Certificados\n" . config('app.name'));
    }

    /**
     * Get the array representation of the notification.
     *
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'report_type' => $this->reportData['report_type'] ?? 'daily',
            'report_date' => $this->reportData['report_date'] ?? now()->toISOString(),
            'total_count' => $this->reportData['total_count'] ?? 0,
            'expired_count' => $this->reportData['expired_count'] ?? 0,
            'critical_count' => $this->reportData['critical_count'] ?? 0,
            'high_count' => $this->reportData['high_count'] ?? 0,
            'medium_count' => $this->reportData['medium_count'] ?? 0,
        ];
    }
}
