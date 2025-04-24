<?php

namespace App\Services\Mail;

class EmailSettingService
{

    public function setDefaultSetting(): void
    {
        // Restablecer el mailer al valor predeterminado
        app()->bind('mailer', function ($app) {
            return $app->make('mail.manager')->mailer();
        });
    }

    /**
     * Sanear una dirección de correo electrónico eliminando caracteres no válidos.
     * @return string
     */


    public static function sanitizeEmailAddress($email): string
    {
        // Eliminar espacios en blanco al inicio y al final
        $email = trim($email);

        // Eliminar caracteres no imprimibles y caracteres Unicode invisibles
        $email = preg_replace('/[\x00-\x1F\x7F-\x9F\xAD\x{200B}-\x{200D}\x{FEFF}]/u', '', $email);

        // Eliminar espacios en blanco Unicode y otros caracteres de espacio
        // Retornar la dirección de correo electrónico saneada
        return preg_replace('/[\pZ\pC]/u', '', $email);
    }


}
