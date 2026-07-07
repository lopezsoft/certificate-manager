<?php

declare(strict_types=1);

namespace App\Http\Controllers\Certificate;

use App\Common\HttpResponseMessages;
use App\Common\MessageExceptionResponse;
use App\DTOs\Certificate\IssuanceRequest;
use App\Exceptions\Certificate\CertificateIssuanceException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Certificate\IssueCertificateRequest;
use App\Modules\Viafirma\Application\Services\ViafirmaDownloadService;
use App\Modules\Viafirma\Application\UseCases\RedownloadCertificateUseCase;
use App\Modules\Viafirma\Domain\Exceptions\ViafirmaException;
use App\Services\Certificate\CertificateIssuanceOrchestrator;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

/**
 * Controlador HTTP unificado para la emisión y consulta de certificados
 * digitales, agnóstico al proveedor (mail/viafirma/…).
 *
 * Rutas expuestas (todas bajo /api/v1/certificate-request/{id}):
 *   - POST  /issue
 *   - GET   /issuance
 *   - GET   /issuance/download
 *   - GET   /issuance/download/file
 *   - POST  /issuance/redownload  [solo ADMIN]
 *
 * ⚠️  DISEÑO INTENCIONAL — Inyección de método (no constructor) para servicios Viafirma:
 *
 *     ViafirmaDownloadService y RedownloadCertificateUseCase NO se inyectan en
 *     el constructor. Estos servicios tiran del stack completo Viafirma:
 *     KeyVault → EncryptedLocalKeyVault (disk I/O en Windows) y
 *     GuzzleViafirmaClient (requiere VIAFIRMA_BASE_URL configurado).
 *
 *     Si se inyectan en el constructor, el controller bloquea/falla al construirse
 *     en CADA request del grupo, incluyendo rutas que no necesitan Viafirma.
 *
 *     Con inyección de método, Laravel los resuelve SOLO cuando la acción
 *     específica (download, downloadFile, redownload) es invocada.
 *
 * Este controller NO conoce los detalles de cada proveedor — sólo orquesta
 * a través de {@see CertificateIssuanceOrchestrator}.
 */
class CertificateIssuanceController extends Controller
{
    public function __construct(
        private readonly CertificateIssuanceOrchestrator $orchestrator,
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
        try {
            $dto    = IssuanceRequest::fromRequest($request, $id);
            $result = $this->orchestrator->dispatch($dto, $request->callerCanOverrideProvider());

            return HttpResponseMessages::getResponseForStatus($result->httpStatus, [
                'message'     => $result->message,
                'dataRecords' => $result->toArray(),
            ]);
        } catch (CertificateIssuanceException $e) {
            return HttpResponseMessages::getResponseForStatus($e->httpStatus, [
                'message'  => $e->getMessage(),
                'provider' => $e->providerName,
            ]);
        } catch (Exception $e) {
            return MessageExceptionResponse::response($e);
        }
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

            return HttpResponseMessages::getResponse([
                'message'     => $result->message,
                'dataRecords' => $result->toArray(),
            ]);
        } catch (CertificateIssuanceException $e) {
            return HttpResponseMessages::getResponseForStatus($e->httpStatus, [
                'message'  => $e->getMessage(),
                'provider' => $e->providerName,
            ]);
        } catch (Exception $e) {
            return MessageExceptionResponse::response($e);
        }
    }

    /**
     * Metadata de descarga (URL temporal + PIN). Sólo aplica a proveedores
     * que generen archivo descargable (hoy: viafirma).
     *
     * @OA\Get(
     *     path="/certificate-request/{uuid}/issuance/download",
     *     operationId="certificateRequestIssuanceDownload",
     *     tags={"Emisión de Certificados"},
     *     summary="Metadata de descarga del P12 (sólo Viafirma)",
     *     description="Retorna el PIN temporal y una URL firmada (24h) para descargar el .p12 ensamblado. Disponible cuando el estado interno es ASSEMBLED o COMPLETED. El parámetro es el UUID público de la solicitud (`certificate_requests.uuid`), no su ID numérico.",
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(name="uuid", in="path", required=true, description="UUID de la solicitud de certificado (certificate_requests.uuid)", @OA\Schema(type="string", format="uuid", example="550e8400-e29b-41d4-a716-446655440000")),
     *     @OA\Response(response=200, description="Metadata + PIN", @OA\JsonContent(ref="#/components/schemas/IssuanceDownloadMetadata")),
     *     @OA\Response(response=404, description="Solicitud o trámite no encontrado", @OA\JsonContent(ref="#/components/schemas/ApiErrorResponse")),
     *     @OA\Response(response=409, description="El estado actual no permite descarga / proveedor no soporta descarga", @OA\JsonContent(ref="#/components/schemas/ApiErrorResponse")),
     *     @OA\Response(response=410, description="El PIN del certificado fue purgado", @OA\JsonContent(ref="#/components/schemas/ApiErrorResponse")),
     *     @OA\Response(response=401, description="No autenticado")
     * )
     */
    public function download(Request $request, string $uuid, ViafirmaDownloadService $viafirmaDownload): JsonResponse
    {
        try {
            // Buscar por UUID para obtener el ID numérico
            $certificateRequest = \App\Models\CertificateRequest::where('uuid', $uuid)->first();
            if ($certificateRequest === null) {
                return HttpResponseMessages::getResponse404([
                    'message' => 'Solicitud de certificado no encontrada.',
                ]);
            }

            // Obtener el archivo desde file_managers (agnóstico del proveedor)
            $certificateFile = \App\Models\FileManager::where('certificate_request_id', $certificateRequest->id)
                ->where('document_type', 'CERTIFICATE')
                ->first();

            if ($certificateFile === null) {
                return HttpResponseMessages::getResponse404([
                    'message' => 'Archivo de certificado no encontrado.',
                ]);
            }

            // Generar URL firmada de S3
            $disk = Storage::disk(config('certificate.storage.disk'));
            if (!$disk->exists($certificateFile->file_path)) {
                return HttpResponseMessages::getResponse404([
                    'message' => 'Archivo no disponible en almacenamiento.',
                ]);
            }

            $temporaryUrl = null;
            try {
                $temporaryUrl = $disk->temporaryUrl($certificateFile->file_path, now()->addHours(24));
            } catch (\RuntimeException) {
                $temporaryUrl = null; // disco local sin soporte de URLs firmadas
            }

            $pin = $certificateRequest->pin ?? 'NO_PIN';

            return HttpResponseMessages::getResponse([
                'dataRecords' => [
                    'data' => [
                        'p12_pin'      => $pin,
                        'p12_filename' => $certificateFile->file_name,
                        'download_url' => $temporaryUrl,
                        'expires_at'   => now()->addHours(24)->toISOString(),
                    ],
                ]
            ]);
        } catch (Exception $e) {
            return MessageExceptionResponse::response($e);
        }
    }

    /**
     * Descarga del P12 codificado en Base64 (uso desatendido por el cliente).
     *
     * @OA\Get(
     *     path="/certificate-request/{uuid}/issuance/download/base64",
     *     operationId="certificateRequestIssuanceDownloadBase64",
     *     tags={"Emisión de Certificados"},
     *     summary="Descarga del P12 en Base64",
     *     description="Devuelve el .p12 codificado en Base64 + PIN, para integración desatendida (ERP, scripts). Sólo proveedor Viafirma en estado ASSEMBLED/COMPLETED. El parámetro es el UUID público de la solicitud (`certificate_requests.uuid`), no su ID numérico.",
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(name="uuid", in="path", required=true, description="UUID de la solicitud de certificado (certificate_requests.uuid)", @OA\Schema(type="string", format="uuid", example="550e8400-e29b-41d4-a716-446655440000")),
     *     @OA\Response(response=200, description="P12 en Base64 + PIN", @OA\JsonContent(
     *         @OA\Property(property="success", type="boolean", example=true),
     *         @OA\Property(property="p12_pin", type="string", example="X3kP9aQ1mZv7nR2s"),
     *         @OA\Property(property="p12_filename", type="string", example="D4AZEQQG6.p12"),
     *         @OA\Property(property="p12_base64", type="string", description="Contenido del .p12 codificado en Base64")
     *     )),
     *     @OA\Response(response=404, description="Solicitud o trámite no encontrado", @OA\JsonContent(ref="#/components/schemas/ApiErrorResponse")),
     *     @OA\Response(response=409, description="El estado actual no permite descarga / proveedor no soporta descarga", @OA\JsonContent(ref="#/components/schemas/ApiErrorResponse")),
     *     @OA\Response(response=410, description="El PIN del certificado fue purgado", @OA\JsonContent(ref="#/components/schemas/ApiErrorResponse")),
     *     @OA\Response(response=401, description="No autenticado")
     * )
     */
    public function downloadBase64(Request $request, string $uuid, ViafirmaDownloadService $viafirmaDownload): JsonResponse
    {
        try {
            // Buscar por UUID para obtener el ID numérico
            $certificateRequest = \App\Models\CertificateRequest::where('uuid', $uuid)->first();
            if ($certificateRequest === null) {
                return HttpResponseMessages::getResponse404([
                    'message' => 'Solicitud de certificado no encontrada.',
                ]);
            }

            // Obtener el archivo desde file_managers (agnóstico del proveedor)
            $certificateFile = \App\Models\FileManager::where('certificate_request_id', $certificateRequest->id)
                ->where('document_type', 'CERTIFICATE')
                ->first();

            if ($certificateFile === null) {
                return HttpResponseMessages::getResponse404([
                    'message' => 'Archivo de certificado no encontrado.',
                ]);
            }

            // Leer contenido desde S3
            $disk = Storage::disk(config('certificate.storage.disk'));
            if (!$disk->exists($certificateFile->file_path)) {
                return HttpResponseMessages::getResponse404([
                    'message' => 'Archivo no disponible en almacenamiento.',
                ]);
            }

            $binary = $disk->get($certificateFile->file_path);
            if ($binary === null || $binary === '') {
                return HttpResponseMessages::getResponse410([
                    'message' => 'No se pudo leer el archivo del almacenamiento.',
                ]);
            }

            $pin = $certificateRequest->pin ?? 'NO_PIN';

            return HttpResponseMessages::getResponse([
                'dataRecords' => [
                    'data' => [
                        'p12_pin'      => $pin,
                        'p12_filename' => $certificateFile->file_name,
                        'p12_base64'   => base64_encode($binary),
                    ],
                ]
            ]);
        } catch (Exception $e) {
            return MessageExceptionResponse::response($e);
        }
    }

    /**
     * Re-descarga el P7B y regenera el P12 con un nuevo PIN. Solo ADMIN.
     *
     * @OA\Post(
     *     path="/certificate-request/{id}/issuance/redownload",
     *     operationId="certificateRequestIssuanceRedownload",
     *     tags={"Emisión de Certificados"},
     *     summary="[ADMIN] Re-descargar P7B y regenerar P12 con nuevo PIN",
     *     description="Consulta el estado remoto en Viafirma, descarga nuevamente el P7B y ensambla un nuevo P12 con PIN renovado. Solo disponible para administradores. Útil cuando el archivo P12 se corrompió o el PIN fue purgado antes de que el cliente lo descargara.",
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(name="id", in="path", required=true, description="ID de la solicitud (certificate_requests.id)", @OA\Schema(type="integer")),
     *     @OA\Response(response=200, description="P12 regenerado exitosamente", @OA\JsonContent(
     *         @OA\Property(property="success", type="boolean", example=true),
     *         @OA\Property(property="message", type="string"),
     *         @OA\Property(property="dataRecords", type="object",
     *             @OA\Property(property="pin", type="string", description="Nuevo PIN para abrir el P12"),
     *             @OA\Property(property="download_url", type="string"),
     *             @OA\Property(property="expires_at", type="string", nullable=true),
     *             @OA\Property(property="viafirma_id", type="integer"),
     *             @OA\Property(property="internal_state", type="string"),
     *             @OA\Property(property="remote_status", type="string")
     *         )
     *     )),
     *     @OA\Response(response=403, description="No es administrador", @OA\JsonContent(ref="#/components/schemas/ApiErrorResponse")),
     *     @OA\Response(response=404, description="Trámite Viafirma no encontrado", @OA\JsonContent(ref="#/components/schemas/ApiErrorResponse")),
     *     @OA\Response(response=409, description="Estado remoto no permite re-descarga", @OA\JsonContent(ref="#/components/schemas/ApiErrorResponse")),
     *     @OA\Response(response=422, description="Llave privada purgada", @OA\JsonContent(ref="#/components/schemas/ApiErrorResponse")),
     *     @OA\Response(response=502, description="Error al consultar Viafirma", @OA\JsonContent(ref="#/components/schemas/ApiErrorResponse")),
     *     @OA\Response(response=401, description="No autenticado")
     * )
     */
    public function redownload(Request $request, int $id, RedownloadCertificateUseCase $useCase): JsonResponse
    {
        if (!$this->callerIsAdmin($request)) {
            return response()->json([
                'success' => false,
                'message' => 'No autorizado. Esta operación requiere permisos de administrador.',
            ], JsonResponse::HTTP_FORBIDDEN);
        }

        try {
            $result = $useCase->handle($id, $request->user()?->id ?? 0);

            return HttpResponseMessages::getResponse([
                'message'     => 'Certificado re-descargado y P12 regenerado exitosamente.',
                'dataRecords' => $result->toArray(),
            ]);
        } catch (ViafirmaException $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], $e->getCode() ?: JsonResponse::HTTP_UNPROCESSABLE_ENTITY);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException) {
            return response()->json([
                'success' => false,
                'message' => "No se encontró un trámite Viafirma para la solicitud {$id}.",
            ], JsonResponse::HTTP_NOT_FOUND);
        } catch (Exception $e) {
            return MessageExceptionResponse::response($e);
        }
    }

    /**
     * Descarga de archivo adjunto por UUID.
     *
     * @OA\Get(
     *     path="/certificate-request/{uuid}/files/{fileUuid}/download",
     *     operationId="certificateRequestFileDownload",
     *     tags={"Archivos"},
     *     summary="Descargar archivo adjunto",
     *     description="Retorna una URL firmada (24h) para descargar un archivo adjunto asociado a la solicitud. Bloquea descarga de tipos sensibles (P7B_CERTIFICATE, PRIVATE_KEY).",
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(name="uuid", in="path", required=true, description="UUID de la solicitud de certificado", @OA\Schema(type="string", format="uuid")),
     *     @OA\Parameter(name="fileUuid", in="path", required=true, description="UUID del archivo (FileManager.uuid)", @OA\Schema(type="string", format="uuid")),
     *     @OA\Response(response=200, description="URL firmada + metadatos", @OA\JsonContent(
     *         @OA\Property(property="success", type="boolean", example=true),
     *         @OA\Property(property="dataRecords", type="object",
     *             @OA\Property(property="data", type="object",
     *                 @OA\Property(property="file_name", type="string", example="documento.pdf"),
     *                 @OA\Property(property="download_url", type="string", format="uri"),
     *                 @OA\Property(property="expires_at", type="string", format="date-time")
     *             )
     *         )
     *     )),
     *     @OA\Response(response=404, description="Solicitud o archivo no encontrado"),
     *     @OA\Response(response=409, description="Tipo de archivo no permitido para descarga"),
     *     @OA\Response(response=401, description="No autenticado")
     * )
     */
    public function downloadFile(Request $request, string $uuid, string $fileUuid): JsonResponse
    {
        try {
            // Buscar solicitud por UUID
            $certificateRequest = \App\Models\CertificateRequest::where('uuid', $uuid)->first();
            if ($certificateRequest === null) {
                return HttpResponseMessages::getResponse404([
                    'message' => 'Solicitud de certificado no encontrada.',
                ]);
            }

            // Buscar archivo por UUID y validar pertenencia
            $file = \App\Models\FileManager::where('uuid', $fileUuid)
                ->where('certificate_request_id', $certificateRequest->id)
                ->first();

            if ($file === null) {
                return HttpResponseMessages::getResponse404([
                    'message' => 'Archivo no encontrado.',
                ]);
            }

            // Bloquear descarga de tipos sensibles
            if (in_array($file->document_type, ['P7B_CERTIFICATE', 'PRIVATE_KEY'])) {
                return HttpResponseMessages::getResponse409([
                    'message' => 'Este tipo de archivo no puede descargarse.',
                ]);
            }

            // Acceder a S3 y generar URL firmada
            $disk = Storage::disk(config('certificate.storage.disk'));
            if (!$disk->exists($file->file_path)) {
                return HttpResponseMessages::getResponse404([
                    'message' => 'Archivo no disponible en almacenamiento.',
                ]);
            }

            $temporaryUrl = null;
            try {
                $temporaryUrl = $disk->temporaryUrl($file->file_path, now()->addHours(24));
            } catch (\RuntimeException) {
                $temporaryUrl = null; // disco local sin soporte de URLs firmadas
            }

            return HttpResponseMessages::getResponse([
                'dataRecords' => [
                    'data' => [
                        'file_name' => $file->file_name,
                        'download_url' => $temporaryUrl,
                        'expires_at' => now()->addHours(24)->toISOString(),
                        'extension_file' => $file->extension_file,
                    ],
                ]
            ]);
        } catch (Exception $e) {
            return MessageExceptionResponse::response($e);
        }
    }

    /**
     * Endpoint para renovar un certificado (genera orden de pago).
     *
     * @OA\Post(
     *     path="/certificate-request/{id}/issuance/renew",
     *     operationId="certificateRequestIssuanceRenew",
     *     tags={"Emisión de Certificados"},
     *     summary="Genera orden de renovación de un certificado",
     *     description="Crea una orden de pago WOMPI para renovar la vigencia del certificado por 1 año adicional (llegando a 2 años de vida comercial total). Retorna la orden creada.",
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(name="id", in="path", required=true, description="ID de la solicitud", @OA\Schema(type="integer")),
     *     @OA\Response(response=200, description="Orden de renovación generada exitosamente"),
     *     @OA\Response(response=400, description="Error al procesar la renovación (ej. ya tiene vida máxima)"),
     *     @OA\Response(response=401, description="No autenticado"),
     *     @OA\Response(response=404, description="Trámite no encontrado")
     * )
     */
    public function renew(Request $request, int $id, \App\Modules\Viafirma\Application\UseCases\RenewCertificateUseCase $useCase): JsonResponse
    {
        try {
            $order = $useCase->handle($id, auth()->id());
            
            return response()->json([
                'success' => true,
                'message' => 'Orden de renovación generada exitosamente.',
                'data'    => $order->toArray(),
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al procesar la renovación: ' . $e->getMessage(),
            ], 400);
        }
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
