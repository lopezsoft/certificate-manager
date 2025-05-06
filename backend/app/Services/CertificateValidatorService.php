<?php

namespace App\Services;

use Exception;
use Illuminate\Support\Facades\Log;

class CertificateValidatorService
{

    /**
     * @throws Exception
     */
    public static function getExpirationDate($data, $password): string
    {
        $cert           = [];
        $expirationDate = null;

        if (!base64_decode($data, true)) {
            throw new Exception('The given data was invalid.', 400);
        }
        if (!openssl_pkcs12_read(base64_decode($data), $cert, $password)) {
            $opensslErrors = [];
            while ($msg = openssl_error_string()) {
                $opensslErrors[] = $msg;
            }
            // Combina los errores de OpenSSL con el último error de PHP (si existe)
            $lastPhpError = error_get_last();
            $errorMessage = 'Error(es) de OpenSSL: ' . (!empty($opensslErrors) ? implode('; ', $opensslErrors) : 'No disponible');
            $logMessage = 'Error al leer el certificado. ' . $errorMessage . '. Último error PHP: ' . json_encode($lastPhpError);

            Log::error($logMessage); // Guarda el mensaje detallado en el log

            // Lanza la excepción con un mensaje más informativo si hay errores de OpenSSL
            $exceptionMessage = !empty($opensslErrors) ? implode('; ', $opensslErrors) : ($lastPhpError ?
                $lastPhpError['message'] : 'Error desconocido al leer el certificado');
            throw new Exception('The certificate could not be read. '.$exceptionMessage, 400);

        }
        if(openssl_pkcs12_read(base64_decode($data), $cert, $password)){
            $cert           = openssl_x509_parse(openssl_x509_read($cert['cert']));
            $expirationDate = date('Y-m-d H:i:s', $cert['validTo_time_t']);
        }
        return $expirationDate;
    }
}
