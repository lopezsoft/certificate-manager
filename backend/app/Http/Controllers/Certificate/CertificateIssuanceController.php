<?php

declare(strict_types=1);

namespace App\Http\Controllers\Certificate;

use App\DTOs\Certificate\IssuanceRequest;
use App\Exceptions\Certificate\CertificateIssuanceException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Certificate\IssueCertificateRequest;
use App\Modules\Viafirma\Application\Services\ViafirmaDownloadService;
use App\Services\Certificate\CertificateIssuanceOrchestrator;
use App\Services\Certificate\Providers\ViafirmaIssuanceProvider;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Controlador HTTP unificado para la emisión y consulta de certificados
 * digitales, agnóstico al proveedor (mail/viafirma/…).
 *
 * Rutas expuestas (todas bajo /api/v1/certificate-request/{id}):
 *   - POST  /issue
 *   - GET   /issuance
 *   - GET   /issuance/download
 *   - GET   /issuance/download/file
 *
 * Este controller NO conoce los detalles de cada proveedor — sólo orquesta
 * a través de {@see CertificateIssuanceOrchestrator}.
 */
class CertificateIssuanceController extends Controller
{
    public function __construct(
        private readonly CertificateIssuanceOrchestrator $orchestrator,
        private readonly ViafirmaDownloadService $viafirmaDownload,
    ) {}

    /**
     * Dispara la emisión del certificado para la solicitud indicada.
     *
     * @OA\Post(
     *     path="/certificate-request/{id}/issue",
     *     operationId="certificateRequestIssue",
     *     tags={"Emisión de Certificados"},
     *     summary="Disparar emisión del certificado (provider-agnostic)",
     *     description="Inicia el flujo de emisión usando el proveedor activo (mail / viafirma / futuros). El proveedor se resuelve por cascada: payload override (admin) → companies.issuance_provider → CERTIFICATE_ISSUANCE_PROVIDER → fallback 'mail'. Para Viafirma el cuerpo debe incluir 'email_certificate'.",
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(name="id", in="path", required=true, description="ID de la solicitud (certificate_requests.id)", @OA\Schema(type="integer")),
     *     @OA\RequestBody(
     *         required=false,
     *         @OA\JsonContent(ref="#/components/schemas/IssueCertificateBody")
     *     ),
     *     @OA\Response(response=200, description="Emisión por correo (mail) ejecutada con éxito", @OA\JsonContent(ref="#/components/schemas/IssuanceResponse")),
     *     @OA\Response(response=201, description="Solicitud Viafirma creada (estado SUBMITTED)", @OA\JsonContent(ref="#/components/schemas/IssuanceResponse")),
     *     @OA\Response(response=404, description="Solicitud no encontrada", @OA\JsonContent(ref="#/components/schemas/ApiErrorResponse")),
     *     @OA\Response(response=409, description="Ya existe un trámite Viafirma activo para esta solicitud", @OA\JsonContent(ref="#/components/schemas/ApiErrorResponse")),
     *     @OA\Response(response=422, description="Validación fallida o proveedor no soporta la solicitud", @OA\JsonContent(ref="#/components/schemas/ApiErrorResponse")),
     *     @OA\Response(response=502, description="Error transitorio del proveedor remoto (Viafirma)", @OA\JsonContent(ref="#/components/schemas/ApiErrorResponse")),
     *     @OA\Response(response=401, description="No autenticado")
     * )
     */
    public function issue(IssueCertificateRequest $request, int $id): JsonResponse
    {
        $dto = IssuanceRequest::fromRequest($request, $id);

        try {
            $result = $this->orchestrator->dispatch($dto, $request->callerCanOverrideProvider());
        } catch (CertificateIssuanceException $e) {
            return response()->json([
                'success'  => false,
                'message'  => $e->getMessage(),
                'provider' => $e->providerName,
            ], $e->httpStatus);
        }

        return response()->json([
            'success' => $result->isSuccess(),
            'message' => $result->message,
            'data'    => $result->toArray(),
        ], $result->httpStatus);
    }


    /**
     * Consulta el estado de emisión usando el proveedor activo.
     *
     * @OA\Get(
     *     path="/certificate-request/{id}/issuance",
     *     operationId="certificateRequestIssuanceShow",
     *     tags={"Emisión de Certificados"},
     *     summary="Consultar estado del trámite",
     *     description="Devuelve el estado normalizado del trámite de emisión. Para Viafirma incluye internal_state, remote_status, public_id y fechas relevantes. Para 'mail' devuelve el request_status actual.",
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\Response(response=200, description="Estado del trámite", @OA\JsonContent(ref="#/components/schemas/IssuanceResponse")),
     *     @OA\Response(response=404, description="Solicitud (o trámite Viafirma) no encontrada", @OA\JsonContent(ref="#/components/schemas/ApiErrorResponse")),
     *     @OA\Response(response=401, description="No autenticado")
     * )
     */
    public function show(Request $request, int $id): JsonResponse
    {
        try {
            $result = $this->orchestrator->status($id, $this->callerIsAdmin($request));
        } catch (CertificateIssuanceException $e) {
            return response()->json([
                'success'  => false,
                'message'  => $e->getMessage(),
                'provider' => $e->providerName,
            ], $e->httpStatus);
        }

        return response()->json([
            'success' => $result->isSuccess(),
            'message' => $result->message,
            'data'    => $result->toArray(),
        ], $result->httpStatus);
    }

    /**
     * Metadata de descarga (URL temporal + PIN). Sólo aplica a proveedores
     * que generen archivo descargable (hoy: viafirma).
     *
     * @OA\Get(
     *     path="/certificate-request/{id}/issuance/download",
     *     operationId="certificateRequestIssuanceDownload",
     *     tags={"Emisión de Certificados"},
     *     summary="Metadata de descarga del P12 (sólo Viafirma)",
     *     description="Retorna el PIN temporal y una URL firmada (24h) para descargar el .p12 ensamblado. Disponible cuando el estado interno es ASSEMBLED o COMPLETED.",
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\Response(response=200, description="Metadata + PIN", @OA\JsonContent(ref="#/components/schemas/IssuanceDownloadMetadata")),
     *     @OA\Response(response=404, description="Trámite no encontrado", @OA\JsonContent(ref="#/components/schemas/ApiErrorResponse")),
     *     @OA\Response(response=409, description="El estado actual no permite descarga / proveedor no soporta descarga", @OA\JsonContent(ref="#/components/schemas/ApiErrorResponse")),
     *     @OA\Response(response=410, description="El PIN del certificado fue purgado", @OA\JsonContent(ref="#/components/schemas/ApiErrorResponse")),
     *     @OA\Response(response=401, description="No autenticado")
     * )
     */
    public function download(Request $request, int $id): JsonResponse
    {
        $provider = $this->orchestrator->providerFor($id, $this->callerIsAdmin($request));

        if ($provider->name() !== ViafirmaIssuanceProvider::NAME) {
            return response()->json([
                'success' => false,
                'message' => "El proveedor '{$provider->name()}' no soporta descarga binaria.",
            ], 409);
        }

        return $this->viafirmaDownload->metadataFor($id, $request->user()?->id);
    }

    /**
     * Streaming binario del archivo P12 (viafirma).
     *
     * @OA\Get(
     *     path="/certificate-request/{id}/issuance/download/file",
     *     operationId="certificateRequestIssuanceDownloadFile",
     *     tags={"Emisión de Certificados"},
     *     summary="Descarga binaria del P12 (streaming)",
     *     description="Streaming directo del archivo .p12 con Content-Disposition: attachment. Sólo proveedor Viafirma en estado ASSEMBLED/COMPLETED.",
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\Response(
     *         response=200,
     *         description="Binario P12",
     *         @OA\MediaType(mediaType="application/x-pkcs12")
     *     ),
     *     @OA\Response(response=404, description="Trámite no encontrado", @OA\JsonContent(ref="#/components/schemas/ApiErrorResponse")),
     *     @OA\Response(response=409, description="El estado actual no permite descarga / proveedor no soporta descarga", @OA\JsonContent(ref="#/components/schemas/ApiErrorResponse")),
     *     @OA\Response(response=401, description="No autenticado")
     * )
     */
    public function downloadFile(Request $request, int $id): StreamedResponse|JsonResponse
    {
        $provider = $this->orchestrator->providerFor($id, $this->callerIsAdmin($request));

        if ($provider->name() !== ViafirmaIssuanceProvider::NAME) {
            return response()->json([
                'success' => false,
                'message' => "El proveedor '{$provider->name()}' no soporta descarga binaria.",
            ], 409);
        }

        return $this->viafirmaDownload->streamFor($id, $request->user()?->id);
    }

    private function callerIsAdmin(Request $request): bool
    {
        $user = $request->user();
        if ($user === null) {
            return false;
        }
        if (method_exists($user, 'hasRole') && $user->hasRole('admin')) {
            return true;
        }
        return (bool) ($user->is_admin ?? false);
    }
}

