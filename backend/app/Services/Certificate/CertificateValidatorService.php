<?php

namespace App\Services\Certificate;

use App\Models\Company;
use App\Models\Settings\Certificate;
use Carbon\Carbon;
use Exception;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
class CertificateValidatorService
{
    /**
     * @throws Exception
     */
    public static function isExpired($company): bool
    {
        $certificate    = Certificate::query()->where('company_id', $company->id)->first();
        if (!$certificate) {
            throw new Exception('No se encontró el certificado.');
        }
        $tz             = 'America/Bogota';
        $password       = $certificate->password;
        $data           = $certificate->data;
        $expirationDate = self::getExpirationDate($data, $password);
        $now            = Carbon::now($tz);
        $date           = Carbon::parse($expirationDate, $tz);
        return !$now->lessThanOrEqualTo($date);
    }

    /**
     * @throws Exception
     */
    public static function validateCertificate($dni): void
    {
        $company        = Company::query()->where('dni', $dni)->first();
        if (!$company) {
            throw new Exception('No se encontró la empresa.', 404);
        }
        $certificate    = Certificate::query()->where('company_id', $company->id)->first();
        if (!$certificate) {
            throw new Exception('No se encontró el certificado.', 404);
        }
        $tz             = 'America/Bogota';
        $password       = $certificate->password;
        $data           = $certificate->data;
        $expirationDate = self::getExpirationDate($data, $password);
        $now            = Carbon::now($tz);
        $date           = Carbon::parse($expirationDate, $tz);
        $isValid        = $now->lessThanOrEqualTo($date);
        if (!$isValid) {
            throw new Exception('El certificado ha expirado.', 400);
        }
    }
    /**
     * @throws Exception
     */
    public static function validate($params): object
    {
        $password           = $params->password;
        $data               = $params->data;
        $company            = $params->company;
        $expirationDate     = self::getExpirationDate($data, $password);
        $certificateBinary  = base64_decode($data);
        $dirCertificates    = 'certificates';
        $name               = "{$company->dni}{$company->dv}.p12";
        $exists             = Storage::exists($dirCertificates);
        if(!$exists){
            Storage::makeDirectory($dirCertificates);
        }
        Storage::put("{$dirCertificates}/{$name}", $certificateBinary);
        return (object) [
            'expirationDate'        => $expirationDate,
            'name'                  => $name,
        ];
    }

    /**
     * @throws Exception
     */
    public static function extractExpiration($dni): ?object
    {
        $company        = Company::query()->where('dni', $dni)->first();
        if (!$company) {
            throw new Exception('No se encontró la empresa.', 404);
        }
        $certificate    = Certificate::query()->where('company_id', $company->id)->first();
        if (!$certificate) {
            throw new Exception('No se encontró el certificado.', 404);
        }
        $password       = $certificate->password;
        $data           = $certificate->data;
        $expirationDate = self::getExpirationDate($data, $password);
        $certificate->expiration_date   = $expirationDate;
        $certificate->save();
        return (Object) [
            'expiration_date'   => $expirationDate,
        ];
    }

    /**
     * @throws Exception
     */
    private static function getExpirationDate($data, $password): string
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
