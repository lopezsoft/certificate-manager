<?php

declare(strict_types=1);

namespace App\Jobs\Certificate;

use App\Modules\Viafirma\Domain\Contracts\CryptoServiceContract;
use App\Modules\Viafirma\Domain\Contracts\KeyVault;
use App\Enums\CertificateRequestStatusEnum;
use App\Events\CertificateStatusChanged;
use App\Models\CertificateRequest;
use App\Models\FileManager;
use App\Services\Certificate\SelfSignedCertificateGenerator;
use App\Services\Certificates\CertificateStoragePathResolver;
use App\Services\CertificateValidatorService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use ZipArchive;
use Exception;

class MockMailCaResponseJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * Número máximo de intentos antes de fallar.
     */
    public int $tries = 1;

    public function __construct(
        private readonly int $certificateRequestId
    ) {}

    public function handle(
        CryptoServiceContract $crypto,
        SelfSignedCertificateGenerator $generator,
        KeyVault $vault,
        CertificateStoragePathResolver $pathResolver
    ): void {
        if (!app()->environment('sandbox')) {
            return; // Salvaguarda: solo ejecuta en sandbox
        }

        $cr = CertificateRequest::query()->find($this->certificateRequestId);
        if (!$cr || $cr->request_status !== CertificateRequestStatusEnum::PROCESSING->value) {
            return; // Si no está PROCESSING, no hacer nada
        }

        // 1. Nombres de archivo (Mismo patrón que AssembleP12Job)
        $sandboxCodRequest = 'SANDBOX-' . strtoupper(Str::random(8));
        $p12Filename       = "{$cr->id}_{$sandboxCodRequest}.p12";
        
        $basePath = rtrim($cr->base_path, '/');
        $zipFilename       = $basePath . '/' . "{$cr->id}_{$sandboxCodRequest}.zip";

        // 2. Generar P12 auto-firmado
        $exportPin = Str::random(32); // PIN CSPRNG para proteger el P12 internamente
        $subject = [
            'CN' => 'SANDBOX ' . ($cr->company_name ?? $cr->dni),
            'O'  => $cr->company_name ?? 'SANDBOX COMPANY',
            'C'  => 'CO',
            'serialNumber' => $cr->dni ?? '000000000',
        ];
        $validityDays = (int) ($cr->life ?? 2) * 365;

        $p12Binary = $generator->generateP12(
            crypto: $crypto,
            subject: $subject,
            validityDays: $validityDays,
            exportPassword: $exportPin,
            friendlyName: $sandboxCodRequest
        );

        // 3. Crear ZIP y proteger con NIT+DV o NIT (Lógica actual de FileStorageService)
        $disk    = $pathResolver->disk();
        $zipPath = Storage::path($zipFilename);

        $zipDir = dirname($zipPath);
        if (!is_dir($zipDir)) {
            mkdir($zipDir, 0755, true);
        }

        $zipPassword = $cr->dni;
        if ($cr->type_organization_id == 1) { // Jurídica
            $zipPassword = "{$cr->dni}{$cr->dv}";
        }

        $zip = new ZipArchive();
        if ($zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            throw new Exception("No se pudo crear el archivo ZIP en: {$zipPath}");
        }

        $zip->setPassword($zipPassword);
        $zip->addFromString($p12Filename, $p12Binary);
        
        // Cifrar el archivo dentro del ZIP (requiere libzip con soporte AES_256, estandar en PHP >= 7.2)
        if (method_exists($zip, 'setEncryptionName')) {
            $zip->setEncryptionName($p12Filename, ZipArchive::EM_AES_256);
        }
        
        $zip->close();

        // Si el storage principal no es local, subir el ZIP a S3 y borrar el temporal local
        if ($disk !== 'local') {
            Storage::disk($disk)->put($zipFilename, file_get_contents($zipPath));
            @unlink($zipPath);
        }

        // 4. Guardar el PIN del P12 en KeyVault y DB
        $vault->store($exportPin, [
            'type'       => 'p12_pin',
            'request_id' => $cr->id,
        ]);

        $validity = CertificateValidatorService::parseValidity($p12Binary, $exportPin);
        $life     = (int) ($cr->life ?: 1);

        $cr->request_status  = CertificateRequestStatusEnum::PROCESSED->value;
        $cr->issued_at       = $validity['validFrom'];
        $cr->cert_valid_to   = $validity['validTo'];
        $cr->expiration_date = $validity['validFrom']->addYears($life);
        $cr->pin             = $exportPin;
        $cr->save();

        // 5. Registrar en File Manager
        FileManager::updateOrCreate(
            [
                'certificate_request_id' => $cr->id,
                'file_name'              => basename($zipFilename),
            ],
            [
                'file_path'      => $zipFilename,
                'extension_file' => 'zip',
                'mime_type'      => 'application/zip',
                'document_type'  => 'CERTIFICATE',
                'file_size'      => Storage::disk($disk)->size($zipFilename),
                'last_modified'  => date('Y-m-d H:i:s', Storage::disk($disk)->lastModified($zipFilename)),
                'status'         => 'COMPLETED',
            ]
        );

        // 6. Lanzar evento real para notificar (webhooks y emails reales)
        event(new CertificateStatusChanged(
            certificateRequestId: $cr->id,
            companyId:            (int) $cr->company_id,
            previousStatus:       CertificateRequestStatusEnum::PROCESSING->value,
            newStatus:            CertificateRequestStatusEnum::PROCESSED->value,
            userId:               0,
            comment:              '[SANDBOX] Certificado auto-firmado generado automáticamente.',
        ));
    }
}
