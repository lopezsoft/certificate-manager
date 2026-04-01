<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Notificación de informe mensual consolidado para administradores
 * 
 * Envía al administrador un reporte global de todos los certificados
 * emitidos durante el mes, agrupados por empresa, estado y vigencia.
 * 
 * @package App\Notifications
 */
class MonthlyAdminCertificatesReportNotification extends Notification implements ShouldQueue
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
        $periodName = $this->reportData['period_name'];
        $total = $this->reportData['total_certificates'];

        $mail = (new MailMessage)
            ->subject("📊 Informe Mensual Consolidado - {$periodName}")
            ->greeting("Informe Mensual de Certificados - {$periodName}")
            ->line("Reporte consolidado de todos los certificados emitidos durante el mes.")
            ->line('')
            ->line('---')
            ->line('')
            ->line("## 📊 RESUMEN EJECUTIVO")
            ->line('')
            ->line("**Periodo:** Del {$this->reportData['period_start']} al {$this->reportData['period_end']}")
            ->line("**Fecha del reporte:** {$this->reportData['report_date']}")
            ->line('')
            ->line("**Total de certificados emitidos:** {$total}")
            ->line("**Total de empresas atendidas:** {$this->reportData['total_companies']}")
            ->line("**Promedio por empresa:** {$this->reportData['stats']['avg_per_company']} certificados")
            ->line('')
            ->line('---')
            ->line('');

        // Estadísticas de vigencia
        $validity = $this->reportData['by_validity'];
        $stats = $this->reportData['stats'];

        $mail->line("## 📈 ESTADO DE VIGENCIA GLOBAL")
            ->line('')
            ->line("✅ **Activos (vigentes):** {$validity['active']} ({$stats['active_percentage']}%)")
            ->line("⚠️ **Próximos a vencer (< 30 días):** {$validity['expiring']}")
            ->line("❌ **Vencidos:** {$validity['expired']}")
            ->line("⏳ **Pendientes de emisión:** {$validity['pending']}")
            ->line('')
            ->line("📊 **Empresas con certificados activos:** {$stats['companies_with_active']}")
            ->line("⚠️ **Empresas con certificados vencidos:** {$stats['companies_with_expired']}")
            ->line('')
            ->line('---')
            ->line('');

        // Distribución por estado
        if (!empty($this->reportData['by_status'])) {
            $mail->line("## 📋 DISTRIBUCIÓN POR ESTADO")
                ->line('');

            foreach ($this->reportData['by_status'] as $statusGroup) {
                $statusName = $this->getStatusName($statusGroup['status']);
                $mail->line("**{$statusName}:** {$statusGroup['count']} certificados ({$statusGroup['percentage']}%)");
            }

            $mail->line('')
                ->line('---')
                ->line('');
        }

        // Top 10 empresas
        if ($this->reportData['top_companies']->isNotEmpty()) {
            $mail->line("## 🏆 TOP 10 EMPRESAS CON MÁS CERTIFICADOS")
                ->line('');

            foreach ($this->reportData['top_companies'] as $index => $company) {
                $position = $index + 1;
                $medal = $position <= 3 ? ['🥇', '🥈', '🥉'][$position - 1] : "#{$position}";
                
                $mail->line("{$medal} **{$company['company_name']}**")
                    ->line("   📊 {$company['count']} certificados emitidos")
                    ->line("   📧 {$company['company_email']}")
                    ->line('');
            }

            $mail->line('---')->line('');
        }

        // Detalle por empresa
        $mail->line("## 🏢 DETALLE POR EMPRESA ({$this->reportData['total_companies']} empresas)")
            ->line('');

        foreach ($this->reportData['by_company']->take(20) as $company) {
            $mail->line("### {$company['company_name']}")
                ->line("📧 {$company['company_email']} | 📊 **{$company['count']} certificado(s)**")
                ->line('');

            // Mostrar primeros 3 certificados de cada empresa
            foreach ($company['certificates']->take(3) as $cert) {
                $statusIcon = $cert['is_expired'] ? '❌' : ($cert['is_expiring_soon'] ? '⚠️' : '✅');
                $mail->line("  {$statusIcon} NIT: {$cert['dni_formatted']} - Vence: {$cert['expiration_date_formatted']} - {$this->getStatusName($cert['request_status'])}");
            }

            if ($company['count'] > 3) {
                $remaining = $company['count'] - 3;
                $mail->line("  *... y {$remaining} certificado(s) más*");
            }

            $mail->line('');
        }

        if ($this->reportData['total_companies'] > 20) {
            $remaining = $this->reportData['total_companies'] - 20;
            $mail->line("*... y {$remaining} empresa(s) más con certificados emitidos*")
                ->line('');
        }

        $mail->line('---')->line('');

        // Alertas y recomendaciones
        $mail->line("## 🎯 ALERTAS Y RECOMENDACIONES")
            ->line('');

        if ($validity['expired'] > 0) {
            $mail->line("- ❌ **CRÍTICO:** {$validity['expired']} certificados vencidos requieren atención inmediata");
        }
        if ($validity['expiring'] > 0) {
            $mail->line("- ⚠️ **IMPORTANTE:** {$validity['expiring']} certificados vencerán en los próximos 30 días");
        }
        if ($stats['companies_with_expired'] > 0) {
            $mail->line("- 📞 **Acción:** Contactar a {$stats['companies_with_expired']} empresas con certificados vencidos");
        }
        if ($stats['companies_with_active'] > 0) {
            $mail->line("- ✅ **Positivo:** {$stats['companies_with_active']} empresas con certificados activos y vigentes");
        }

        $mail->line('')
            ->line('---')
            ->line('')
            ->line("## 📈 ANÁLISIS DEL PERIODO")
            ->line('')
            ->line("Durante el mes de **{$periodName}** se emitieron **{$total} certificados** para **{$this->reportData['total_companies']} empresas**.");

        if ($stats['active_percentage'] >= 70) {
            $mail->line("✅ Excelente: El {$stats['active_percentage']}% de los certificados están activos y vigentes.");
        } elseif ($stats['active_percentage'] >= 50) {
            $mail->line("⚠️ Atención: Solo el {$stats['active_percentage']}% de los certificados están activos.");
        } else {
            $mail->line("🚨 Crítico: Solo el {$stats['active_percentage']}% de los certificados están activos. Se requiere acción inmediata.");
        }

        $mail->line('')
            ->line('---')
            ->line('')
            ->action('Acceder al Panel de Administración', config('app.frontend_url', config('app.url')))
            ->line('')
            ->line("*Este es un informe automático generado por el sistema de gestión de certificados.*")
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
        $periodName = $this->reportData['period_name'];

        return (new MailMessage)
            ->subject("📊 Informe Mensual - {$periodName} (Sin Actividad)")
            ->greeting("Informe Mensual - {$periodName}")
            ->line("**Periodo:** Del {$this->reportData['period_start']} al {$this->reportData['period_end']}")
            ->line('')
            ->line("ℹ️ **Información:**")
            ->line('')
            ->line("No se emitieron certificados durante el periodo de **{$periodName}**.")
            ->line('')
            ->line("Este informe se genera automáticamente al final de cada mes.")
            ->line('')
            ->action('Acceder al Panel de Administración', config('app.frontend_url', config('app.url')))
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
            'period_name' => $this->reportData['period_name'] ?? '',
            'total_certificates' => $this->reportData['total_certificates'] ?? 0,
            'total_companies' => $this->reportData['total_companies'] ?? 0,
            'active' => $this->reportData['by_validity']['active'] ?? 0,
            'expiring' => $this->reportData['by_validity']['expiring'] ?? 0,
            'expired' => $this->reportData['by_validity']['expired'] ?? 0,
        ];
    }

    /**
     * Obtener nombre legible del estado
     *
     * @param string $status
     * @return string
     */
    private function getStatusName(string $status): string
    {
        $statusMap = [
            'pending' => 'Pendiente',
            'in_progress' => 'En Proceso',
            'approved' => 'Aprobado',
            'rejected' => 'Rechazado',
            'issued' => 'Emitido',
            'delivered' => 'Entregado',
            'cancelled' => 'Cancelado',
        ];

        return $statusMap[$status] ?? ucfirst($status);
    }
}
