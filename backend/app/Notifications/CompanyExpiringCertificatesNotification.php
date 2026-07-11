<?php

namespace App\Notifications;

use App\Exports\CertificateExpirationReportExport;
use App\Models\Company;
use Carbon\Carbon;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Collection;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Maatwebsite\Excel\Facades\Excel;

/**
 * Notificación consolidada de certificados próximos a vencer para una empresa
 *
 * Envía un único correo a la empresa con un resumen breve y un archivo Excel
 * adjunto contiendo todos los certificados de esa empresa próximos a vencer.
 */
class CompanyExpiringCertificatesNotification extends Notification implements ShouldQueue
{
    use Queueable;

    private Company $company;
    private Collection $certificates;

    /**
     * Create a new notification instance.
     *
     * @param Company $company
     * @param Collection $certificates
     */
    public function __construct(Company $company, Collection $certificates)
    {
        $this->company = $company;
        $this->certificates = $certificates;
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
        $criticalCount = $this->countByUrgency('critical');
        $highCount = $this->countByUrgency('high');
        $mediumCount = $this->countByUrgency('medium');
        $expiredCount = $this->countByUrgency('expired');

        $subject = $this->buildSubject($expiredCount, $criticalCount);
        $replyTo = config('mail.reply_to.address');

        $mail = (new MailMessage)
            ->replyTo($replyTo)
            ->subject($subject)
            ->greeting("Estimado cliente,")
            ->line("Le informamos que tiene certificados digitales próximos a vencer.")
            ->line('')
            ->line("**Resumen:**")
            ->line("Empresa: {$this->company->name}")
            ->line("Certificados por vencer: {$this->certificates->count()}")
            ->line('');

        if ($expiredCount > 0) {
            $mail->line("🚨 **Vencidos:** {$expiredCount}");
        }
        if ($criticalCount > 0) {
            $mail->line("🔴 **Críticos (1-7 días):** {$criticalCount}");
        }
        if ($highCount > 0) {
            $mail->line("🟠 **Alta Prioridad (8-15 días):** {$highCount}");
        }
        if ($mediumCount > 0) {
            $mail->line("🟡 **Media Prioridad (16-30 días):** {$mediumCount}");
        }

        $mail->line('')
            ->line("Para ver el detalle completo de todos los certificados, consulte el archivo adjunto.")
            ->line('')
            ->line("Le recomendamos iniciar el proceso de renovación lo antes posible para evitar interrupciones en sus operaciones.")
            ->action('Acceder al Sistema', config('app.frontend_url', config('app.url')))
            ->line('')
            ->line("**Información de Contacto:**")
            ->line("📧 Email: " . config('mail.support_address', config('mail.from.address')))
            ->line("📱 Teléfono: " . ($this->company->phone ?? 'N/A'))
            ->line('')
            ->salutation("Atentamente,\n" . config('app.name'));

        return $this->attachExcel($mail);
    }

    /**
     * Attach the Excel file to the mail message.
     *
     * @param MailMessage $mail
     * @return MailMessage
     */
    private function attachExcel(MailMessage $mail): MailMessage
    {
        try {
            $filename = 'certificados-por-vencer-' . now()->format('Y-m-d-His') . '.xlsx';

            $excel = Excel::raw(
                new CertificateExpirationReportExport($this->certificates),
                \Maatwebsite\Excel\Excel::XLSX
            );

            return $mail->attachData(
                $excel,
                $filename,
                ['mime' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet']
            );
        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::error('[CertificateExpiration] Error generando Excel adjunto', [
                'company_id' => $this->company->id,
                'error' => $e->getMessage()
            ]);

            return $mail;
        }
    }

    /**
     * Count certificates by urgency level.
     *
     * @param string $level
     * @return int
     */
    private function countByUrgency(string $level): int
    {
        return $this->certificates->filter(function ($cert) use ($level) {
            $daysRemaining = now()->diffInDays(Carbon::parse($cert->expiration_date), false);

            return match ($level) {
                'expired' => $daysRemaining <= 0,
                'critical' => $daysRemaining > 0 && $daysRemaining <= 7,
                'high' => $daysRemaining > 7 && $daysRemaining <= 15,
                'medium' => $daysRemaining > 15 && $daysRemaining <= 30,
                default => false,
            };
        })->count();
    }

    /**
     * Build the email subject based on urgency.
     *
     * @param int $expiredCount
     * @param int $criticalCount
     * @return string
     */
    private function buildSubject(int $expiredCount, int $criticalCount): string
    {
        if ($expiredCount > 0) {
            return "🚨 URGENTE: {$expiredCount} certificado(s) vencido(s) - {$this->company->name}";
        } elseif ($criticalCount > 0) {
            return "🔴 CRÍTICO: {$criticalCount} certificado(s) vencerá(n) en 7 días - {$this->company->name}";
        }

        return "📌 Certificados próximos a vencer - {$this->company->name}";
    }

    /**
     * Get the array representation of the notification.
     *
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'company_id' => $this->company->id,
            'company_name' => $this->company->name,
            'certificates_count' => $this->certificates->count(),
        ];
    }
}
