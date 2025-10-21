<?php

namespace App\Notifications;

use App\Models\CertificateRequest;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Carbon\Carbon;

/**
 * Notificación para alertar a las empresas sobre certificados próximos a vencer
 * 
 * Esta notificación se envía automáticamente cuando un certificado está
 * próximo a vencer (dentro de los siguientes 30 días).
 * 
 * @package App\Notifications
 */
class CertificateExpiringNotification extends Notification implements ShouldQueue
{
    use Queueable;

    private CertificateRequest $certificate;
    private int $daysRemaining;
    private string $urgencyLevel;

    /**
     * Create a new notification instance.
     *
     * @param CertificateRequest $certificate
     * @param int $daysRemaining
     * @param string $urgencyLevel
     */
    public function __construct(
        CertificateRequest $certificate,
        int $daysRemaining,
        string $urgencyLevel
    ) {
        $this->certificate = $certificate;
        $this->daysRemaining = $daysRemaining;
        $this->urgencyLevel = $urgencyLevel;
        $this->onQueue('notifications');
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
        $expirationDate = Carbon::parse($this->certificate->expiration_date);
        $formattedDate = $expirationDate->locale('es')->isoFormat('D [de] MMMM [de] YYYY');
        
        $subject = $this->getSubjectByUrgency();
        $greeting = $this->getGreetingByUrgency();
        $actionColor = $this->getActionColorByUrgency();

        return (new MailMessage)
            ->subject($subject)
            ->greeting($greeting)
            ->line("Le informamos que su certificado digital está próximo a vencer.")
            ->line('')
            ->line("**Detalles del Certificado:**")
            ->line("🏢 **Empresa:** {$this->certificate->company_name}")
            ->line("🆔 **NIT/DNI:** {$this->formatDni()}")
            ->line("📅 **Fecha de Vencimiento:** {$formattedDate}")
            ->line("⏰ **Días Restantes:** {$this->daysRemaining} días")
            ->line('')
            ->line($this->getUrgencyMessage())
            ->line('')
            ->line("Para evitar interrupciones en sus operaciones, le recomendamos iniciar el proceso de renovación lo antes posible.")
            ->action('Acceder al Sistema', config('app.frontend_url', config('app.url')))
            ->line('')
            ->line("**Información de Contacto:**")
            ->line("📧 Email: " . config('mail.support_address', config('mail.from.address')))
            ->line("📱 Teléfono: {$this->certificate->phone}")
            ->line('')
            ->salutation("Atentamente,\n" . config('app.name'))
            ->theme('default')
            ->metadata('urgency', $this->urgencyLevel)
            ->metadata('certificate_id', $this->certificate->id);
    }

    /**
     * Get the array representation of the notification.
     *
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'certificate_id' => $this->certificate->id,
            'company_name' => $this->certificate->company_name,
            'dni' => $this->certificate->dni,
            'expiration_date' => $this->certificate->expiration_date,
            'days_remaining' => $this->daysRemaining,
            'urgency_level' => $this->urgencyLevel,
        ];
    }

    /**
     * Obtener asunto según urgencia
     *
     * @return string
     */
    private function getSubjectByUrgency(): string
    {
        $companyName = $this->certificate->company_name;
        
        return match ($this->urgencyLevel) {
            'critical' => "🚨 URGENTE: Su certificado vence en {$this->daysRemaining} días - {$companyName}",
            'high' => "⚠️ IMPORTANTE: Su certificado vence pronto - {$companyName}",
            'medium' => "📌 Recordatorio: Renovación de certificado - {$companyName}",
            default => "📋 Aviso: Certificado próximo a vencer - {$companyName}",
        };
    }

    /**
     * Obtener saludo según urgencia
     *
     * @return string
     */
    private function getGreetingByUrgency(): string
    {
        return match ($this->urgencyLevel) {
            'critical' => '🚨 ALERTA CRÍTICA',
            'high' => '⚠️ ATENCIÓN IMPORTANTE',
            'medium' => '📌 Estimado Cliente',
            default => 'Hola',
        };
    }

    /**
     * Obtener color de acción según urgencia
     *
     * @return string
     */
    private function getActionColorByUrgency(): string
    {
        return match ($this->urgencyLevel) {
            'critical' => 'error',
            'high' => 'warning',
            default => 'primary',
        };
    }

    /**
     * Obtener mensaje de urgencia
     *
     * @return string
     */
    private function getUrgencyMessage(): string
    {
        return match ($this->urgencyLevel) {
            'critical' => '🚨 **ACCIÓN INMEDIATA REQUERIDA:** Su certificado vencerá en los próximos ' . $this->daysRemaining . ' días. Es crítico que inicie el proceso de renovación de inmediato para evitar la suspensión de sus servicios.',
            'high' => '⚠️ **ATENCIÓN:** Su certificado vencerá en ' . $this->daysRemaining . ' días. Le recomendamos programar la renovación con urgencia para evitar contratiempos.',
            'medium' => '📌 **RECORDATORIO:** Su certificado vencerá en ' . $this->daysRemaining . ' días. Le sugerimos planificar la renovación en los próximos días para garantizar continuidad en sus operaciones.',
            default => 'Su certificado vencerá en ' . $this->daysRemaining . ' días. Le recordamos iniciar el proceso de renovación oportunamente.',
        };
    }

    /**
     * Formatear DNI/NIT con dígito verificador
     *
     * @return string
     */
    private function formatDni(): string
    {
        $dni = $this->certificate->dni;
        $dv = $this->certificate->dv;
        
        if ($dv) {
            return number_format($dni, 0, '', '.') . "-{$dv}";
        }
        
        return number_format($dni, 0, '', '.');
    }
}
