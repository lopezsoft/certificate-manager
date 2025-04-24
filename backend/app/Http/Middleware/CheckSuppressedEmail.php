<?php

namespace App\Http\Middleware;

use App\Models\Company;
use Closure;
use App\Models\SuppressedEmail; // Importa tu modelo
use Illuminate\Support\Facades\Log;
use Symfony\Component\Mime\Email; // Importante: para el type hint
use Symfony\Component\Mime\Address; // Importante: para el type hint
use App\Notifications\BlockedSendAttemptNotification;
use Illuminate\Support\Facades\Notification; // Importa el Facade de Notificación

class CheckSuppressedEmail
{
    /**
     * Handle an outgoing email message.
     *
     * @param Email $message
     * @param  Closure  $next
     * @return mixed
     */
    public function handle(Email $message, Closure $next): mixed
    {
        // 1. Obtener todas las direcciones de destinatarios (To, Cc, Bcc)
        $recipients = collect($message->getTo())
            ->merge($message->getCc())
            ->merge($message->getBcc())
            ->map(fn (Address $address) => $address->getAddress()) // Extraer solo el email
            ->filter() // Eliminar posibles nulos/vacíos
            ->unique() // No verificar la misma dirección múltiples veces
            ->values() // Resetear keys del array/colección
            ->all();

        Log::info('Recipientes a verificar: ' . json_encode($recipients));

        if (empty($recipients)) {
            // No hay destinatarios, dejar pasar (aunque esto sería raro)
            return $next($message);
        }

        // 2. Consultar la tabla de supresión eficientemente
        $suppressedFound = SuppressedEmail::whereIn('email', $recipients)
            ->pluck('email') // Obtener solo los emails suprimidos encontrados
            ->all();
        Log::info('Direcciones suprimidas encontradas: ' . json_encode($suppressedFound));

        // 3. Decidir qué hacer si se encuentran direcciones suprimidas
        if (!empty($suppressedFound)) {
            // --- INICIO: Notificar a la COMPAÑÍA ---
            $companyDniHeader = $message->getHeaders()->getHeaderBody('X-DNI-COMPANY');
            Log::info('X-DNI-COMPANY: ' . $companyDniHeader);
            Log::info('headers: ' . json_encode($message->getHeaders()->toString()));

            if ($companyDniHeader) {
                $company = Company::where('dni', $companyDniHeader)->first();

                if ($company) {
                    $messageData = (object) [
                        'originalSubject'   => $message->getSubject(),
                        'suppressedEmails'  => $suppressedFound,
                        'company'           => $company,
                    ];
                    Notification::route('mail', 'soporte@matias.com.co')
                        ->notify(new BlockedSendAttemptNotification(
                            $messageData
                        ));
                    $settings   = collect($company->settings ?? []);
                    $notificationMail = null;
                    foreach ($settings as $setting) {
                        if ($setting->setting->key_value === 'NOTIFICATIONEMAIL') {
                            $notificationMail = $setting->value;
                        }
                    }
                    if(empty($notificationMail)) {
                        $notificationMail = $company->email;
                    }
                    if(!empty($notificationMail)) {
                        Notification::route('mail', $notificationMail)->notify(new BlockedSendAttemptNotification($messageData));
                    }
                }
            }
            return null;
        }

        // 4. Si no hay suprimidos, continuar con el envío
        return $next($message);
    }
}
