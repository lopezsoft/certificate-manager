<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Notificación de informe mensual de certificados para empresas
 * 
 * Envía a cada empresa un reporte detallado de todos los certificados
 * emitidos durante el mes, segmentados por estado y vigencia.
 * 
 * @package App\Notifications
 */
class MonthlyCompanyCertificatesReportNotification extends Notification implements ShouldQueue
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
        $companyName = $this->reportData['company']->company_name;
        $periodName = $this->reportData['period_name'];
        $total = $this->reportData['total_certificates'];

        $mail = (new MailMessage)
            ->subject("📊 Informe Mensual de Certificados - {$periodName}")
            ->greeting("Estimado cliente: {$companyName}")
            ->line("Le presentamos el informe mensual de certificados emitidos durante **{$periodName}**.")
            ->line('')
            ->line('---')
            ->line('')
            ->line("## 📊 RESUMEN GENERAL")
            ->line('')
            ->line("**Periodo:** Del {$this->reportData['period_start']} al {$this->reportData['period_end']}")
            ->line("**Total de certificados emitidos:** {$total}")
            ->line('')
            ->line('---')
            ->line('');

        // Estadísticas de vigencia
        $validity = $this->reportData['by_validity'];
        $stats = $this->reportData['stats'];

        $mail->line("## 📈 ESTADO DE VIGENCIA")
            ->line('')
            ->line("✅ **Activos (vigentes):** {$validity['active']['count']} ({$stats['active_percentage']}%)")
            ->line("⚠️ **Próximos a vencer (< 30 días):** {$validity['expiring']['count']}")
            ->line("❌ **Vencidos:** {$validity['expired']['count']}")
            ->line("⏳ **Pendientes de emisión:** {$validity['pending']['count']}")
            ->line('')
            ->line('---')
            ->line('');

        // Detalle por estado
        if ($this->reportData['by_status']->isNotEmpty()) {
            $mail->line("## 📋 CERTIFICADOS POR ESTADO")
                ->line('');

            foreach ($this->reportData['by_status'] as $statusGroup) {
                $statusName = $this->getStatusName($statusGroup['status']);
                $mail->line("**{$statusName}:** {$statusGroup['count']} certificado(s)");
                
                if ($statusGroup['certificates']->isNotEmpty()) {
                    foreach ($statusGroup['certificates']->take(5) as $cert) {
                        $mail->line("  • NIT: {$cert['dni_formatted']} - Vence: {$cert['expiration_date_formatted']}");
                    }
                    
                    if ($statusGroup['count'] > 5) {
                        $remaining = $statusGroup['count'] - 5;
                        $mail->line("  *... y {$remaining} certificado(s) más*");
                    }
                }
                
                $mail->line('');
            }
            
            $mail->line('---')->line('');
        }

        // Certificados activos
        if ($validity['active']['count'] > 0) {
            $mail->line("## ✅ CERTIFICADOS ACTIVOS ({$validity['active']['count']})")
                ->line('')
                ->line("Certificados en plena vigencia:")
                ->line('');

            foreach ($validity['active']['certificates']->take(10) as $cert) {
                $mail->line("• **NIT:** {$cert['dni_formatted']}")
                    ->line("  Vence: {$cert['expiration_date_formatted']} ({$cert['days_remaining']} días restantes)")
                    ->line("  Estado: {$this->getStatusName($cert['request_status'])}")
                    ->line('');
            }

            if ($validity['active']['count'] > 10) {
                $remaining = $validity['active']['count'] - 10;
                $mail->line("*... y {$remaining} certificado(s) activo(s) más*")->line('');
            }

            $mail->line('---')->line('');
        }

        // Certificados próximos a vencer
        if ($validity['expiring']['count'] > 0) {
            $mail->line("## ⚠️ CERTIFICADOS PRÓXIMOS A VENCER ({$validity['expiring']['count']})")
                ->line('')
                ->line("**ATENCIÓN:** Los siguientes certificados vencerán en los próximos 30 días:")
                ->line('');

            foreach ($validity['expiring']['certificates'] as $cert) {
                $urgency = $cert['days_remaining'] <= 7 ? '🚨 URGENTE' : '⚠️';
                $mail->line("{$urgency} **NIT:** {$cert['dni_formatted']}")
                    ->line("  Vence: {$cert['expiration_date_formatted']} (⏰ {$cert['days_remaining']} días restantes)")
                    ->line("  Rep. Legal: {$cert['legal_representative']}")
                    ->line('');
            }

            $mail->line("💡 **Recomendación:** Le sugerimos iniciar el proceso de renovación lo antes posible.")
                ->line('')
                ->line('---')
                ->line('');
        }

        // Certificados vencidos
        if ($validity['expired']['count'] > 0) {
            $mail->line("## ❌ CERTIFICADOS VENCIDOS ({$validity['expired']['count']})")
                ->line('')
                ->line("**IMPORTANTE:** Los siguientes certificados ya han vencido:")
                ->line('');

            foreach ($validity['expired']['certificates']->take(10) as $cert) {
                $daysExpired = abs($cert['days_remaining']);
                $mail->line("❌ **NIT:** {$cert['dni_formatted']}")
                    ->line("  Vencido el: {$cert['expiration_date_formatted']} (hace {$daysExpired} días)")
                    ->line('');
            }

            if ($validity['expired']['count'] > 10) {
                $remaining = $validity['expired']['count'] - 10;
                $mail->line("*... y {$remaining} certificado(s) vencido(s) más*")->line('');
            }

            $mail->line("⚠️ **Acción requerida:** Contacte con nosotros para renovar estos certificados.")
                ->line('')
                ->line('---')
                ->line('');
        }

        // Recomendaciones
        $mail->line("## 🎯 RECOMENDACIONES")
            ->line('');

        if ($validity['expiring']['count'] > 0) {
            $mail->line("- ⚠️ **{$validity['expiring']['count']} certificado(s)** próximo(s) a vencer - Programar renovación inmediata");
        }
        if ($validity['expired']['count'] > 0) {
            $mail->line("- ❌ **{$validity['expired']['count']} certificado(s)** vencido(s) - Requiere acción urgente");
        }
        if ($validity['active']['count'] > 0) {
            $mail->line("- ✅ **{$validity['active']['count']} certificado(s)** activo(s) y vigente(s)");
        }

        $mail->line('')
            ->line('---')
            ->line('')
            ->action('Acceder al Sistema', config('app.frontend_url', config('app.url')))
            ->line('')
            ->line("Si tiene alguna pregunta o necesita asistencia, no dude en contactarnos:")
            ->line("📧 Email: " . config('mail.from.address'))
            ->line("📱 Soporte: " . config('mail.from.address'))
            ->line('')
            ->line("*Este es un informe automático generado por el sistema de gestión de certificados.*")
            ->salutation("Atentamente,\n" . config('app.name'));

        return $mail;
    }

    /**
     * Get the array representation of the notification.
     *
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'company_id' => $this->reportData['company']->id,
            'period_name' => $this->reportData['period_name'],
            'total_certificates' => $this->reportData['total_certificates'],
            'active_count' => $this->reportData['stats']['active_count'],
            'expiring_count' => $this->reportData['stats']['expiring_count'],
            'expired_count' => $this->reportData['stats']['expired_count'],
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
