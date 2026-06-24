<?php

declare(strict_types=1);

namespace App\Modules\Viafirma\Infrastructure\Http;

use App\Modules\Viafirma\Application\DTOs\ProfileDescriptor;
use App\Modules\Viafirma\Application\DTOs\StatusResultDto;
use App\Modules\Viafirma\Application\DTOs\SubmitCsrInputDto;
use App\Modules\Viafirma\Application\DTOs\SubmitCsrResultDto;
use App\Modules\Viafirma\Domain\Contracts\ViafirmaClient;
use App\Modules\Viafirma\Domain\Enums\RemoteStatus;
use Illuminate\Support\Facades\Cache;

/**
 * Cliente Mock de Viafirma para entorno Sandbox.
 *
 * Simula las respuestas del API de Viafirma para poder probar todo el
 * ciclo de vida (emisión, polling, descarga y revocación) sin generar
 * certificados reales ni interactuar con la infraestructura externa.
 */
class MockViafirmaClient implements ViafirmaClient
{
    public function getProfiles(string $raCode): array
    {
        return [
            new ProfileDescriptor(
                codProfile: config('viafirma.cod_profile_corporate', 'FE-PJ'),
                name: 'Certificado Representante Legal (Sandbox)',
                dnPattern: 'CN=#1#, O=#2#, C=CO',
                validity: 730,
                token: 'mock-token-corporate',
                raw: ['mock' => true, 'profile' => 'corporate'],
            ),
            new ProfileDescriptor(
                codProfile: config('viafirma.cod_profile_individual', 'FE-PN'),
                name: 'Certificado Persona Natural (Sandbox)',
                dnPattern: 'CN=#1#, C=CO',
                validity: 730,
                token: 'mock-token-individual',
                raw: ['mock' => true, 'profile' => 'individual'],
            ),
        ];
    }

    public function submitCsr(SubmitCsrInputDto $input): SubmitCsrResultDto
    {
        $codRequest = 'MOCK-REQ-' . strtoupper(uniqid());
        $publicId   = 'MOCK-PUB-' . strtoupper(uniqid());

        // Inicializamos el estado simulado en Cache.
        // Empezará en RUES_CHECK (progressing) y avanzará en polls.
        Cache::put("mock_viafirma_status_{$codRequest}", [
            'polls'    => 0,
            'publicId' => $publicId,
        ], now()->addHours(2));

        return new SubmitCsrResultDto(
            codRequest:    $codRequest,
            publicId:      $publicId,
            initialStatus: RemoteStatus::RUES_CHECK->value, // estado inicial válido (progressing)
            raw: [
                'codRequest' => $codRequest,
                'publicId'   => $publicId,
                'mock'       => true,
            ],
        );
    }

    public function getStatus(string $codRequest): StatusResultDto
    {
        $cacheKey = "mock_viafirma_status_{$codRequest}";
        $state = Cache::get($cacheKey);

        if (!$state) {
            // Si no existe (quizás expiró o es un ID dummy manual), devolvemos éxito directo.
            return new StatusResultDto(
                status: RemoteStatus::GENERATED_NOT_DOWNLOADED,
                codRequest: $codRequest,
                raw: ['mock' => true, 'default_success' => true]
            );
        }

        $state['polls']++;
        Cache::put($cacheKey, $state, now()->addHours(2));

        // Simulamos demora realista usando estados válidos del enum RemoteStatus:
        // Poll 1 -> rues_check  (progressing)
        // Poll 2 -> inProcess   (progressing)
        // Poll 3+ -> Generated_Not_Downloaded (listo!)
        $status = match (true) {
            $state['polls'] === 1 => RemoteStatus::RUES_CHECK,
            $state['polls'] === 2 => RemoteStatus::IN_PROCESS,
            default              => RemoteStatus::GENERATED_NOT_DOWNLOADED,
        };

        return new StatusResultDto(
            status:    $status,
            codRequest: $codRequest,
            raw: ['mock' => true, 'polls' => $state['polls'], 'simulated_status' => $status->value]
        );
    }

    public function downloadP7b(string $publicId): string
    {
        // Retornamos una cadena Base64 dummy válida sintácticamente como P7B falso,
        // o simplemente un string dummy (el CryptoService local debería poder manejar fallos
        // si intenta parsearlo, pero para el Sandbox es suficiente devolver un string de prueba).
        // En este caso devolvemos la palabra "MOCK_P7B_DATA" codificada en Base64 para simular binario.
        return base64_encode('MOCK_P7B_DATA_FOR_PUBLIC_ID_' . $publicId);
    }

    public function revokeCertificate(string $revokingCode, int $revocationReason): string
    {
        return 'MOCK-REVOKED-REQ-' . strtoupper(uniqid());
    }

    public function getAccreditationLink(string $codRequest): string
    {
        return 'https://sandbox.viafirma.com/accreditation/success?req=' . $codRequest;
    }

    public function getRevocationCode(string $codRequest): string
    {
        return 'MOCK-REV-CODE-' . strtoupper(substr(md5($codRequest), 0, 8));
    }
}
