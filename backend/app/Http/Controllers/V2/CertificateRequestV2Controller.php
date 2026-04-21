<?php

namespace App\Http\Controllers\V2;

use App\Andes\Contracts\AndesPkiServiceContract;
use App\Andes\Jobs\PollAndesCertificateStatusJob;
use App\Andes\Models\AndesCertificateRequest;
use App\Andes\Services\AndesDataMapper;
use App\Andes\Exceptions\AndesCertificateEmissionException;
use App\Http\Controllers\Controller;
use App\Models\CertificateRequest;
use App\Quotas\Services\QuotaService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * CertificateRequestV2Controller — Sprint 3
 *
 * Crea solicitudes de certificado usando el proveedor ANDES SCD.
 * Flujo: verificar cupo → crear solicitud → homologar datos → emitir SOAP → polling.
 */
class CertificateRequestV2Controller extends Controller
{
    public function __construct(
        private readonly AndesPkiServiceContract $pkiService,
        private readonly AndesDataMapper         $mapper,
        private readonly QuotaService            $quotaService,
    ) {}

    /**
     * @OA\Post(
     *     path="/v2/certificate-request",
     *     tags={"v2 - Solicitudes ANDES"},
     *     summary="Crear solicitud de certificado ANDES (Facturación Electrónica)",
     *     security={{"bearerAuth":{}}},
     *     @OA\RequestBody(required=true, @OA\JsonContent(
     *         required={"certificate_request_id","formato","vigencia","soporte_base64"},
     *         @OA\Property(property="certificate_request_id", type="integer"),
     *         @OA\Property(property="formato", type="integer", enum={2,3,4}, description="2=Token físico, 3=PKCS10, 4=Virtual"),
     *         @OA\Property(property="vigencia", type="integer", enum={1,2}, description="Años de vigencia"),
     *         @OA\Property(property="soporte_base64", type="string", description="ZIP en base64 con documentos de soporte"),
     *         @OA\Property(property="pin", type="string", nullable=true, description="PIN mínimo 10 chars alfanuméricos"),
     *     )),
     *     @OA\Response(response=201, description="Solicitud enviada a ANDES"),
     *     @OA\Response(response=402, description="Sin cupo disponible"),
     *     @OA\Response(response=422, description="Error de validación")
     * )
     */
    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'certificate_request_id' => ['required', 'integer', 'exists:certificate_requests,id'],
            'formato'                => ['required', 'integer', 'in:2,3,4'],
            'vigencia'               => ['required', 'integer', 'in:1,2'],
            'soporte_base64'         => ['required', 'string'],
            'pin'                    => ['nullable', 'string', 'min:10', 'regex:/^[a-zA-Z0-9]+$/'],
        ]);

        $certReq = CertificateRequest::with([
            'identity', 'organization', 'city', 'company',
        ])->findOrFail($data['certificate_request_id']);

        $companyId = $certReq->company_id;

        // ── 1. Verificar disponibilidad de cupo ───────────────────────────────
        if (! $this->quotaService->hasAvailableQuota($companyId)) {
            return response()->json([
                'message' => 'La empresa no tiene cupo disponible. Adquiera certificados o solicite cupo al administrador.',
                'code'    => 'QUOTA_EXHAUSTED',
            ], 402);
        }

        return DB::transaction(function () use ($certReq, $data, $companyId) {
            // ── 2. Consumir cupo ──────────────────────────────────────────────
            $this->quotaService->consumeQuota($companyId);

            // ── 3. Marcar solicitud como ANDES ────────────────────────────────
            $certReq->update([
                'provider_type'  => 'ANDES',
                'request_status' => 'ANDES_SUBMITTED',
            ]);

            // ── 4. Homologar datos → DTO SOAP ─────────────────────────────────
            $dto = $this->mapper->buildCertificateEmissionRequest(
                cert:          $certReq,
                formato:       $data['formato'],
                vigenciaYears: $data['vigencia'],
                soporteBase64: $data['soporte_base64'],
                pin:           $data['pin'] ?? null,
            );

            // ── 5. Llamar a ANDES PKI SOAP ────────────────────────────────────
            try {
                $emissionResponse = $this->pkiService->requestElectronicInvoiceCertificate($dto);
            } catch (AndesCertificateEmissionException $e) {
                Log::error('[V2] Error al enviar a ANDES PKI.', ['error' => $e->getMessage()]);
                // Devolver cupo si falló el envío
                $this->quotaService->releaseQuota($companyId);
                $certReq->update(['provider_type' => 'CAMERFIRMA', 'request_status' => 'DRAFT']);
                return response()->json(['message' => 'Error al comunicarse con ANDES. Intente de nuevo.'], 502);
            }

            // ── 6. Persitir en andes_certificate_requests ────────────────────
            $andesReq = AndesCertificateRequest::create([
                'certificate_request_id' => $certReq->id,
                'andes_solicitud_id'     => $emissionResponse->solicitudId,
                'tipo_cert'              => $dto->tipoCert,
                'formato'                => $dto->formato,
                'vigencia_cert'          => $dto->vigenciaCert,
                'andes_estado'           => $emissionResponse->estado,
                'andes_message'          => $emissionResponse->message,
                'andes_raw_response'     => $emissionResponse->rawResponse,
                'pin_hash'               => $data['pin'] ? bcrypt($data['pin']) : null,
            ]);

            // Actualizar número de solicitud ANDES en certificate_requests
            if ($emissionResponse->solicitudId) {
                $certReq->update(['andes_request_number' => $emissionResponse->solicitudId]);
            }

            // ── 7. Encolar polling job ────────────────────────────────────────
            PollAndesCertificateStatusJob::dispatch($andesReq->id)
                ->delay(now()->addSeconds(config('andes.polling_interval', 3600)));

            Log::info('[V2] Solicitud ANDES enviada correctamente.', [
                'andes_solicitud_id' => $emissionResponse->solicitudId,
                'certificate_request_id' => $certReq->id,
            ]);

            return response()->json([
                'data' => [
                    'andes_certificate_request_id' => $andesReq->id,
                    'andes_solicitud_id'           => $emissionResponse->solicitudId,
                    'estado'                       => $emissionResponse->estado,
                    'message'                      => $emissionResponse->message,
                    'polling_active'               => true,
                ],
            ], 201);
        });
    }

    /**
     * @OA\Get(
     *     path="/v2/certificate-request/{id}",
     *     tags={"v2 - Solicitudes ANDES"},
     *     summary="Consultar estado de solicitud ANDES",
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\Response(response=200, description="Estado de la solicitud")
     * )
     */
    public function show(int $id): JsonResponse
    {
        $andesReq = AndesCertificateRequest::with([
            'certificateRequest.company',
            'latestValidation',
        ])->findOrFail($id);

        return response()->json([
            'data' => [
                'id'                     => $andesReq->id,
                'andes_solicitud_id'     => $andesReq->andes_solicitud_id,
                'tipo_cert'              => $andesReq->tipo_cert,
                'andes_estado'           => $andesReq->andes_estado,
                'andes_message'          => $andesReq->andes_message,
                'certificate_serial'     => $andesReq->certificate_serial,
                'emitted_at'             => $andesReq->emitted_at?->toIso8601String(),
                'revoked_at'             => $andesReq->revoked_at?->toIso8601String(),
                'is_emitted'             => $andesReq->isEmitted(),
                'is_revoked'             => $andesReq->isRevoked(),
                'latest_validation'      => $andesReq->latestValidation ? [
                    'estado'       => $andesReq->latestValidation->estado,
                    'validated_at' => $andesReq->latestValidation->validated_at?->toIso8601String(),
                ] : null,
            ],
        ]);
    }
}

