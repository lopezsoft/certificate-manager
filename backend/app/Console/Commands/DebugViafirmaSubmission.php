<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\CertificateRequest;
use App\Modules\Viafirma\Infrastructure\Persistence\Models\ViafirmaCertificateRequest;
use Illuminate\Console\Command;

/**
 * Muestra los datos EXACTOS que fueron enviados a Viafirma para una solicitud.
 *
 * Uso: php artisan debug:viafirma-submission {certificate_request_id}
 *      php artisan debug:viafirma-submission {viafirma_request_id} --viafirma
 */
final class DebugViafirmaSubmission extends Command
{
    protected $signature = 'debug:viafirma-submission {id : ID de CertificateRequest o ViafirmaCertificateRequest} {--viafirma : Si el ID es de ViafirmaCertificateRequest}';
    protected $description = 'Muestra los datos EXACTOS que fueron enviados a Viafirma';

    public function handle(): int
    {
        $id = $this->argument('id');
        $isViafirmaId = $this->option('viafirma');

        if ($isViafirmaId) {
            $viafirmaReq = ViafirmaCertificateRequest::with('state', 'certificateRequest')->find($id);
            if (!$viafirmaReq) {
                $this->error("ViafirmaCertificateRequest {$id} no encontrada");
                return self::FAILURE;
            }
            $cr = $viafirmaReq->certificateRequest;
            $state = $viafirmaReq->state;
        } else {
            $cr = CertificateRequest::find($id);
            if (!$cr) {
                $this->error("CertificateRequest {$id} no encontrada");
                return self::FAILURE;
            }

            $viafirmaReq = ViafirmaCertificateRequest::with('state')->where('certificate_request_id', $cr->id)->latest()->first();
            if (!$viafirmaReq) {
                $this->error("CertificateRequest {$id} no tiene solicitud Viafirma asociada");
                return self::FAILURE;
            }
            $state = $viafirmaReq->state;
        }

        $this->info('═════════════════════════════════════════════════════════════');
        $this->info('DATOS ENVIADOS A VIAFIRMA');
        $this->info('═════════════════════════════════════════════════════════════');

        // ── Información de la solicitud ──
        $profileType = $viafirmaReq->profile_type instanceof \App\Modules\Viafirma\Domain\Enums\CertificateProfile
            ? $viafirmaReq->profile_type->value
            : $viafirmaReq->profile_type;
        $internalState = $state->internal_state instanceof \App\Modules\Viafirma\Domain\Enums\InternalState
            ? $state->internal_state->value
            : $state->internal_state;

        $this->line("\n📋 SOLICITUD:");
        $this->line("  • Certificate Request ID: {$cr->id}");
        $this->line("  • Viafirma Request ID: {$viafirmaReq->id}");
        $this->line("  • Código Solicitud: {$viafirmaReq->cod_request}");
        $this->line("  • Perfil: {$profileType}");
        $this->line("  • Estado: {$internalState}");

        // ── Payload enviado ──
        $payload = $state->request_payload;
        if (!$payload) {
            $this->error("No hay payload guardado para esta solicitud");
            return self::FAILURE;
        }

        $payloadArray = is_string($payload) ? json_decode($payload, true) : $payload;
        if (!is_array($payloadArray)) {
            $this->error("Payload no es un JSON válido");
            return self::FAILURE;
        }

        $this->line("\n📨 PAYLOAD ENVIADO A VIAFIRMA:");
        $this->newLine();

        foreach ($payloadArray as $key => $value) {
            if ($key === 'csr') {
                // Mostrar información del CSR, no el contenido completo
                $this->line("  • {$key}: [base64, " . strlen($value) . " caracteres]");
                $this->line("    Primeros 50 chars: " . substr($value, 0, 50) . "...");
            } else {
                $this->line("  • {$key}: {$value}");
            }
        }

        // ── Validación contra datos en BD ──
        $this->line("\n✅ VALIDACIÓN CONTRA BD:");
        $this->newLine();

        // Convertir enums a strings
        $identityType = $viafirmaReq->identity_type instanceof \App\Modules\Viafirma\Domain\Enums\IdentityType
            ? $viafirmaReq->identity_type->value
            : $viafirmaReq->identity_type;
        $organizationType = $viafirmaReq->organization_type instanceof \App\Modules\Viafirma\Domain\Enums\OrganizationType
            ? $viafirmaReq->organization_type->value
            : $viafirmaReq->organization_type;

        $checks = [
            'identityType' => [
                'payload' => $payloadArray['identityType'] ?? 'N/A',
                'bd' => $identityType,
            ],
            'countryCode' => [
                'payload' => $payloadArray['countryCode'] ?? 'N/A',
                'bd' => $viafirmaReq->country_code,
            ],
            'identity' => [
                'payload' => $payloadArray['identity'] ?? 'N/A',
                'bd_dni' => $cr->dni . " (NIT empresa)",
                'bd_doc_number' => $cr->document_number . " (cédula representante)",
            ],
            'ra' => [
                'payload' => $payloadArray['ra'] ?? 'N/A',
                'bd' => $viafirmaReq->ra_code,
            ],
            'organizationType' => [
                'payload' => $payloadArray['organizationType'] ?? '(no aplica para FE-PN)',
                'bd' => $organizationType,
            ],
        ];

        foreach ($checks as $field => $check) {
            $this->line("  📌 {$field}:");
            foreach ($check as $source => $value) {
                $this->line("     • {$source}: {$value}");
            }

            // Validación especial para identity
            if ($field === 'identity') {
                $payloadIdentity = $payloadArray['identity'] ?? null;
                $expectedIdentity = $viafirmaReq->profile_type === 'FE-PJ' ? $cr->dni : $cr->document_number;

                if ($payloadIdentity === $expectedIdentity) {
                    $this->line("     ✅ CORRECTO - identity coincide con lo esperado");
                } else {
                    $this->line("     ❌ INCORRECTO - identity NO coincide");
                    $this->line("        Esperado: {$expectedIdentity}");
                    $this->line("        Enviado: {$payloadIdentity}");
                }
            }
        }

        // ── Información del CSR ──
        $this->line("\n🔐 CSR ENVIADO:");
        if ($state->csr_pem) {
            $this->line("  • Tamaño: " . strlen($state->csr_pem) . " caracteres");
            $this->line("  • Fingerprint: {$state->csr_fingerprint}");
            $this->line("  • Tiene headers PEM: " . (strpos($state->csr_pem, 'BEGIN') !== false ? 'Sí' : 'No'));
        } else {
            $this->line("  • CSR no está disponible");
        }

        $this->line("\n" . str_repeat('═', 61));
        $this->info('Si el error RUES persiste, compara los valores arriba con lo que está en RUES');
        $this->line(str_repeat('═', 61));

        return self::SUCCESS;
    }
}
