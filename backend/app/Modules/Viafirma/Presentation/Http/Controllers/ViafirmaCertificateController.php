<?php

declare(strict_types=1);

namespace App\Modules\Viafirma\Presentation\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Viafirma\Application\Commands\IssueCertificateCommand;
use App\Modules\Viafirma\Application\UseCases\IssueCertificateUseCase;
use App\Modules\Viafirma\Domain\Contracts\ViafirmaCertificateRequestRepositoryContract;
use App\Modules\Viafirma\Domain\Enums\IdentityType;
use App\Modules\Viafirma\Domain\Enums\OrganizationType;
use App\Modules\Viafirma\Domain\Contracts\KeyVault;
use App\Modules\Viafirma\Domain\Enums\InternalState;
use App\Modules\Viafirma\Domain\Exceptions\ViafirmaException;
use App\Modules\Viafirma\Infrastructure\Persistence\Models\ViafirmaCertificateRequest;
use App\Modules\Viafirma\Presentation\Http\Requests\IssueCertificateFormRequest;
use App\Modules\Viafirma\Presentation\Http\Resources\ViafirmaCertificateResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Controller REST para la emisión de certificados Viafirma (V-207).
 *
 * Endpoints:
 *  - POST /api/v2/certificates/viafirma/issue → Iniciar emisión Zero-Touch PKCS#10
 *  - GET  /api/v2/certificates/viafirma/{id}  → Consultar estado de una solicitud
 *  - GET  /api/v2/certificates/viafirma       → Listar solicitudes (paginado)
 */
class ViafirmaCertificateController extends Controller
{
    public function __construct(
        private readonly IssueCertificateUseCase $issueCertificateUseCase,
        private readonly ViafirmaCertificateRequestRepositoryContract $repository,
        private readonly LoggerInterface $logger,
    ) {}

    /**
     * @OA\Post(
     *     path="/certificates/viafirma/issue",
     *     tags={"v2 - Viafirma Certificados"},
     *     summary="Iniciar emisión de certificado digital Zero-Touch PKCS#10",
     *     description="Orquesta el flujo completo: resolución de perfil (FE-PJ/FE-PN), generación de llave RSA-2048, construcción de CSR, envío a Viafirma RA y persistencia. La llave privada nunca sale del servidor.",
     *     security={{"bearerAuth":{}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(ref="#/components/schemas/IssueCertificateBody")
     *     ),
     *     @OA\Response(
     *         response=201,
     *         description="Solicitud Viafirma creada exitosamente",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Solicitud Viafirma creada exitosamente."),
     *             @OA\Property(property="data", ref="#/components/schemas/ViafirmaCertificateRequest")
     *         )
     *     ),
     *     @OA\Response(response=422, description="Validación fallida", @OA\JsonContent(ref="#/components/schemas/ApiErrorResponse")),
     *     @OA\Response(response=409, description="Ya existe un trámite Viafirma para esta solicitud", @OA\JsonContent(ref="#/components/schemas/ApiErrorResponse")),
     *     @OA\Response(response=502, description="Error de comunicación con Viafirma RA", @OA\JsonContent(ref="#/components/schemas/ApiErrorResponse")),
     *     @OA\Response(response=401, description="No autenticado")
     * )
     */
    public function issue(IssueCertificateFormRequest $request): JsonResponse
    {
        $validated = $request->validated();

        $command = new IssueCertificateCommand(
            certificateRequestId: (int) $validated['certificate_request_id'],
            requestedByUserId:    $request->user()?->id,
            emailCertificate:     $validated['email_certificate'],
            organizationType:     isset($validated['organization_type'])
                ? OrganizationType::from($validated['organization_type'])
                : null,
            identityTypeOverride: isset($validated['identity_type_override'])
                ? IdentityType::from($validated['identity_type_override'])
                : null,
        );

        try {
            $entity = $this->issueCertificateUseCase->handle($command);

            return response()->json([
                'success' => true,
                'message' => 'Solicitud Viafirma creada exitosamente.',
                'data'    => new ViafirmaCertificateResource(
                    $entity->load(['certificateRequest', 'company'])
                ),
            ], 201);
        } catch (ViafirmaException $e) {
            $this->logger->warning('viafirma.issue.domain_error', [
                'cr_id'   => $validated['certificate_request_id'],
                'message' => $e->getMessage(),
            ]);

            $statusCode = str_contains($e->getMessage(), 'ya tiene un trámite') ? 409 : 422;

            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], $statusCode);
        } catch (\App\Modules\Viafirma\Domain\Exceptions\TransientHttpException $e) {
            $this->logger->error('viafirma.issue.transient_error', [
                'cr_id'   => $validated['certificate_request_id'],
                'message' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Error de comunicación con Viafirma RA. Intente nuevamente.',
            ], 502);
        } catch (\App\Modules\Viafirma\Domain\Exceptions\ViafirmaClientException $e) {
            $this->logger->error('viafirma.issue.client_error', [
                'cr_id'   => $validated['certificate_request_id'],
                'message' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Error del servicio Viafirma: ' . $e->getMessage(),
            ], 422);
        }
    }

    /**
     * @OA\Get(
     *     path="/certificates/viafirma/{id}",
     *     tags={"v2 - Viafirma Certificados"},
     *     summary="Consultar estado de solicitud Viafirma",
     *     description="Retorna el detalle de una solicitud Viafirma con su estado interno, estado remoto, historial y datos de la solicitud de negocio asociada.",
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(name="id", in="path", required=true, description="ID del registro viafirma_certificate_requests", @OA\Schema(type="integer")),
     *     @OA\Response(
     *         response=200,
     *         description="Detalle de la solicitud Viafirma",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="data", ref="#/components/schemas/ViafirmaCertificateRequest")
     *         )
     *     ),
     *     @OA\Response(response=404, description="Solicitud no encontrada"),
     *     @OA\Response(response=401, description="No autenticado")
     * )
     */
    public function show(int $id): JsonResponse
    {
        $entity = ViafirmaCertificateRequest::query()
            ->with(['certificateRequest.company', 'certificateRequest.identity', 'certificateRequest.organization', 'company', 'statusHistory'])
            ->findOrFail($id);

        return response()->json([
            'success' => true,
            'data'    => new ViafirmaCertificateResource($entity),
        ]);
    }

    /**
     * @OA\Get(
     *     path="/certificates/viafirma",
     *     tags={"v2 - Viafirma Certificados"},
     *     summary="Listar solicitudes Viafirma (paginado)",
     *     description="Retorna las solicitudes Viafirma filtradas por estado y/o empresa. Paginación estándar de Laravel.",
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(name="internal_state", in="query", description="Filtrar por estado interno (DRAFT,SUBMITTED,POLLING,READY_TO_DOWNLOAD,COMPLETED,FAILED,...)", @OA\Schema(type="string")),
     *     @OA\Parameter(name="profile_type", in="query", description="Filtrar por perfil (FE-PJ, FE-PN)", @OA\Schema(type="string")),
     *     @OA\Parameter(name="company_id", in="query", description="Filtrar por empresa", @OA\Schema(type="integer")),
     *     @OA\Parameter(name="per_page", in="query", description="Registros por página (default: 15, max: 100)", @OA\Schema(type="integer")),
     *     @OA\Response(
     *         response=200,
     *         description="Lista paginada de solicitudes Viafirma",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="data", type="array", @OA\Items(ref="#/components/schemas/ViafirmaCertificateRequest")),
     *             @OA\Property(property="meta", ref="#/components/schemas/PaginationMeta")
     *         )
     *     ),
     *     @OA\Response(response=401, description="No autenticado")
     * )
     */
    public function index(Request $request): JsonResponse
    {
        $query = ViafirmaCertificateRequest::query()
            ->with(['certificateRequest', 'company'])
            ->latest();

        // Filtros opcionales
        if ($request->filled('internal_state')) {
            $query->where('internal_state', $request->input('internal_state'));
        }
        if ($request->filled('profile_type')) {
            $query->where('profile_type', $request->input('profile_type'));
        }
        if ($request->filled('company_id')) {
            $query->where('company_id', (int) $request->input('company_id'));
        }

        $perPage = min((int) ($request->input('per_page', 15)), 100);
        $paginated = $query->paginate($perPage);

        return response()->json([
            'success' => true,
            'data'    => ViafirmaCertificateResource::collection($paginated->items()),
            'meta'    => [
                'current_page' => $paginated->currentPage(),
                'last_page'    => $paginated->lastPage(),
                'per_page'     => $paginated->perPage(),
                'total'        => $paginated->total(),
            ],
        ]);
    }

    /**
     * @OA\Get(
     *     path="/certificates/viafirma/{id}/download",
     *     tags={"v2 - Viafirma Certificados"},
     *     summary="Descargar certificado P12 ensamblado",
     *     description="Descarga el archivo .p12 ensamblado para la solicitud indicada. Solo disponible cuando el estado interno es ASSEMBLED o COMPLETED. Retorna un JSON con metadata + PIN (temporal) o un streaming del binario según el header Accept.",
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(name="id", in="path", required=true, description="ID del registro viafirma_certificate_requests", @OA\Schema(type="integer")),
     *     @OA\Response(
     *         response=200,
     *         description="Metadata de descarga con PIN temporal",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="p12_pin", type="string", description="PIN para abrir el P12"),
     *             @OA\Property(property="download_url", type="string", description="URL temporal firmada para descarga directa"),
     *             @OA\Property(property="expires_at", type="string", format="date-time")
     *         )
     *     ),
     *     @OA\Response(response=404, description="Solicitud no encontrada"),
     *     @OA\Response(response=409, description="Estado no permite descarga"),
     *     @OA\Response(response=401, description="No autenticado")
     * )
     */
    public function download(int $id, KeyVault $vault): JsonResponse
    {
        $entity = ViafirmaCertificateRequest::findOrFail($id);

        // Guard: solo descargar si está en ASSEMBLED o COMPLETED
        if (!in_array($entity->internal_state, [InternalState::ASSEMBLED, InternalState::COMPLETED], true)) {
            return response()->json([
                'success' => false,
                'message' => "El certificado no está disponible para descarga en estado {$entity->internal_state->value}.",
            ], 409);
        }

        if (empty($entity->p12_storage_path)) {
            return response()->json([
                'success' => false,
                'message' => 'El archivo P12 no se ha generado aún.',
            ], 409);
        }

        if (empty($entity->p12_password_ref) || $entity->p12_password_ref === 'PURGED') {
            return response()->json([
                'success' => false,
                'message' => 'El PIN del certificado ha sido purgado. Contacte soporte.',
            ], 410);
        }

        // Obtener PIN del KeyVault
        $pin = $vault->retrieve($entity->p12_password_ref);

        // Generar URL temporal firmada (24h)
        $disk = config('viafirma.storage.p12_disk', 'local');
        $temporaryUrl = null;

        try {
            $temporaryUrl = Storage::disk($disk)->temporaryUrl(
                $entity->p12_storage_path,
                now()->addHours(24),
            );
        } catch (\RuntimeException $e) {
            // Disk local no soporta temporaryUrl — enviar URL del endpoint
            $temporaryUrl = null;
        }

        $this->logger->info('viafirma.download.served', [
            'id'   => $entity->id,
            'user' => request()->user()?->id,
        ]);

        return response()->json([
            'success'      => true,
            'p12_pin'      => $pin,
            'p12_filename' => basename($entity->p12_storage_path),
            'download_url' => $temporaryUrl,
            'expires_at'   => now()->addHours(24)->toISOString(),
        ]);
    }

    /**
     * @OA\Get(
     *     path="/certificates/viafirma/{id}/download/file",
     *     tags={"v2 - Viafirma Certificados"},
     *     summary="Streaming binario del P12",
     *     description="Descarga directa del binario P12 con Content-Disposition attachment.",
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\Response(response=200, description="Binario P12"),
     *     @OA\Response(response=404, description="No encontrado"),
     *     @OA\Response(response=409, description="Estado no permite descarga")
     * )
     */
    public function downloadFile(int $id): StreamedResponse|JsonResponse
    {
        $entity = ViafirmaCertificateRequest::findOrFail($id);

        if (!in_array($entity->internal_state, [InternalState::ASSEMBLED, InternalState::COMPLETED], true)) {
            return response()->json([
                'success' => false,
                'message' => "Estado {$entity->internal_state->value} no permite descarga.",
            ], 409);
        }

        if (empty($entity->p12_storage_path)) {
            return response()->json([
                'success' => false,
                'message' => 'Archivo P12 no disponible.',
            ], 409);
        }

        $disk     = config('viafirma.storage.p12_disk', 'local');
        $filename = basename($entity->p12_storage_path);

        $this->logger->info('viafirma.download.file_streamed', [
            'id'   => $entity->id,
            'user' => request()->user()?->id,
        ]);

        return Storage::disk($disk)->download($entity->p12_storage_path, $filename, [
            'Content-Type'        => 'application/x-pkcs12',
            'Content-Disposition' => "attachment; filename=\"{$filename}\"",
        ]);
    }
}
