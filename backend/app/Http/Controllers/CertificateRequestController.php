<?php

namespace App\Http\Controllers;

use App\Http\Requests\Certificate\CreateCertificateRequestFormRequest;
use App\Http\Requests\Certificate\UpdateCertificateRequestFormRequest;
use App\DTOs\CertificateRequestFiltersDTO;
use App\Services\CertificateRequestService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CertificateRequestController extends Controller
{
    public function __construct(
        private readonly CertificateRequestService $service,
    ) {}

    /**
     * @OA\Post(
     *     path="/certificate-request",
     *     tags={"Solicitudes de Certificado"},
     *     summary="Crear solicitud de certificado",
     *     description="Crea una nueva solicitud de certificado digital. Requiere adjuntar entre 2 y 3 archivos (PDF/imagen). Límite: 10 solicitudes/minuto.",
     *     security={{"bearerAuth":{}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\MediaType(
     *             mediaType="multipart/form-data",
     *             @OA\Schema(
     *                 schema="CreateCertificateRequestBody",
     *                 required={"city_id","identity_document_id","type_organization_id","document_number","address","legal_representative","company_name","dni","life"},
     *                 @OA\Property(property="city_id", type="integer", example=149),
     *                 @OA\Property(property="identity_document_id", type="integer", example=1),
     *                 @OA\Property(property="type_organization_id", type="integer", example=1),
     *                 @OA\Property(property="entity_document_type_id", type="integer", example=1, description="1=Cámara de Comercio, 2=Personería Jurídica, etc. (Opcional, default 1)"),
     *                 @OA\Property(property="document_number", type="string", example="1234567890"),
     *                 @OA\Property(property="address", type="string", example="Calle 123 # 45-67"),
     *                 @OA\Property(property="legal_representative", type="string", example="JUAN PÉREZ"),
     *                 @OA\Property(property="legal_rep_email", type="string", example="juan.perez@empresa.com", description="Requerido si viafirma y persona jurídica", nullable=true),
     *                 @OA\Property(property="company_name", type="string", example="MI EMPRESA S.A.S."),
     *                 @OA\Property(property="dni", type="string", example="900455420"),
     *                 @OA\Property(property="life", type="integer", example=1),
     *                 @OA\Property(property="info", type="string", nullable=true)
     *             )
     *         )
     *     ),
     *     @OA\Response(response=200, description="Solicitud creada exitosamente", @OA\JsonContent(ref="#/components/schemas/ApiSuccessResponse")),
     *     @OA\Response(response=400, description="Validación fallida o solicitud duplicada", @OA\JsonContent(ref="#/components/schemas/ApiErrorResponse")),
     *     @OA\Response(response=402, description="Sin cupo disponible — debe adquirir certificados", @OA\JsonContent(ref="#/components/schemas/ApiErrorResponse")),
     *     @OA\Response(response=429, description="Demasiadas solicitudes", @OA\JsonContent(ref="#/components/schemas/ApiErrorResponse")),
     *     @OA\Response(response=401, description="No autenticado")
     * )
     */
    public function createCertificateRequest(CreateCertificateRequestFormRequest $request): JsonResponse
    {
        return $this->service->createCertificateRequest($request);
    }

    /**
     * @OA\Get(
     *     path="/certificate-request",
     *     tags={"Solicitudes de Certificado"},
     *     summary="Listar mis solicitudes",
     *     description="Retorna las solicitudes de la empresa autenticada. Estados: DRAFT|SENT|PENDING|ACCEPTED|PROCESSING|PROCESSED|REJECTED",
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(name="request_status", in="query", description="Filtrar por estado del certificado", @OA\Schema(type="string")),
     *     @OA\Parameter(name="query", in="query", description="Búsqueda por razón social, NIT, documento o representante", @OA\Schema(type="string")),
     *     @OA\Parameter(name="start_date", in="query", description="Fecha inicio filtro (Y-m-d)", @OA\Schema(type="string")),
     *     @OA\Parameter(name="end_date", in="query", description="Fecha fin filtro (Y-m-d)", @OA\Schema(type="string")),
     *     @OA\Parameter(name="limit", in="query", description="Registros por página (default: 15)", @OA\Schema(type="integer")),
     *     @OA\Response(response=200, description="Lista paginada de solicitudes",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="dataRecords", type="object",
     *                 @OA\Property(property="data", type="array", @OA\Items(ref="#/components/schemas/CertificateRequest")),
     *                 @OA\Property(property="meta", ref="#/components/schemas/PaginationMeta")
     *             )
     *         )
     *     ),
     *     @OA\Response(response=401, description="No autenticado")
     * )
     */
    public function getCertificateRequest(Request $request): JsonResponse
    {
        return $this->service->getCertificateRequest(CertificateRequestFiltersDTO::fromRequest($request));
    }

    /**
     * @OA\Get(
     *     path="/certificate-request/all",
     *     tags={"Solicitudes de Certificado"},
     *     summary="Listar todas las solicitudes (administrador)",
     *     description="Retorna todas las solicitudes activas de todas las empresas.",
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(name="request_status", in="query", @OA\Schema(type="string")),
     *     @OA\Parameter(name="query", in="query", @OA\Schema(type="string")),
     *     @OA\Parameter(name="limit", in="query", @OA\Schema(type="integer")),
     *     @OA\Response(response=200, description="Lista de solicitudes",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="dataRecords", type="object",
     *                 @OA\Property(property="data", type="array", @OA\Items(ref="#/components/schemas/CertificateRequest")),
     *                 @OA\Property(property="meta", ref="#/components/schemas/PaginationMeta")
     *             )
     *         )
     *     ),
     *     @OA\Response(response=401, description="No autenticado")
     * )
     */
    public function getAllCertificateRequest(Request $request): JsonResponse
    {
        return $this->service->getAllCertificateRequest(CertificateRequestFiltersDTO::fromRequest($request));
    }

    /**
     * @OA\Get(
     *     path="/certificate-request/{id}",
     *     tags={"Solicitudes de Certificado"},
     *     summary="Obtener detalle de una solicitud",
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\Response(response=200, description="Detalle de la solicitud", @OA\JsonContent(ref="#/components/schemas/CertificateRequest")),
     *     @OA\Response(response=401, description="No autenticado")
     * )
     */
    public function getCertificateRequestById(int $id): JsonResponse
    {
        return $this->service->getCertificateRequestById($id);
    }

    /**
     * @OA\Put(
     *     path="/certificate-request/{id}",
     *     tags={"Solicitudes de Certificado"},
     *     summary="Actualizar solicitud de certificado",
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\RequestBody(required=true, @OA\JsonContent(ref="#/components/schemas/CertificateRequest")),
     *     @OA\Response(response=200, description="Solicitud actualizada", @OA\JsonContent(ref="#/components/schemas/ApiSuccessResponse")),
     *     @OA\Response(response=422, description="Validación fallida"),
     *     @OA\Response(response=401, description="No autenticado")
     * )
     */
    public function updateCertificateRequest(UpdateCertificateRequestFormRequest $request, $id): JsonResponse
    {
        return $this->service->updateCertificateRequest($request, $id);
    }

    /**
     * @OA\Put(
     *     path="/certificate-request/{id}/status",
     *     tags={"Solicitudes de Certificado"},
     *     summary="Actualizar estado de una solicitud",
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             schema="UpdateStatusBody",
     *             required={"request_status"},
     *             @OA\Property(property="request_status", type="string", example="PROCESSING", description="DRAFT|SENT|PENDING|ACCEPTED|PROCESSING|PROCESSED|REJECTED"),
     *             @OA\Property(property="comments", type="string", nullable=true),
     *             @OA\Property(property="user_of_change", type="string", example="MANAGER", description="USER|MANAGER")
     *         )
     *     ),
     *     @OA\Response(response=200, description="Estado actualizado", @OA\JsonContent(ref="#/components/schemas/ApiSuccessResponse")),
     *     @OA\Response(response=401, description="No autenticado")
     * )
     */
    public function updateCertificateRequestStatus(Request $request, $id): JsonResponse
    {
        return $this->service->updateCertificateRequestStatus($request, $id);
    }

    /**
     * @OA\Delete(
     *     path="/certificate-request/{id}",
     *     tags={"Solicitudes de Certificado"},
     *     summary="Eliminar solicitud de certificado",
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\Response(response=200, description="Solicitud eliminada", @OA\JsonContent(ref="#/components/schemas/ApiSuccessResponse")),
     *     @OA\Response(response=401, description="No autenticado")
     * )
     */
    public function deleteCertificateRequest($id): JsonResponse
    {
        return $this->service->deleteCertificateRequest($id);
    }

    /**
     * @OA\Get(
     *     path="/certificate-request/lookup/{dni}",
     *     tags={"Solicitudes de Certificado"},
     *     summary="Consultar última solicitud por NIT",
     *     description="Busca la solicitud más reciente con el NIT proporcionado dentro de la empresa autenticada. Útil para autocompletar el formulario de nueva solicitud.",
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(name="dni", in="path", required=true, description="NIT/Cédula a consultar", @OA\Schema(type="string", example="901091403")),
     *     @OA\Response(
     *         response=200,
     *         description="Datos de la última solicitud encontrada",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="Datos de la última solicitud encontrada"),
     *             @OA\Property(property="dataRecords", type="object",
     *                 @OA\Property(property="city_id", type="integer", example=149),
     *                 @OA\Property(property="identity_document_id", type="integer", example=3),
     *                 @OA\Property(property="type_organization_id", type="integer", example=1),
     *                 @OA\Property(property="entity_document_type_id", type="integer", example=1),
     *                 @OA\Property(property="dni", type="string", example="901091403"),
     *                 @OA\Property(property="dv", type="string", example="2"),
     *                 @OA\Property(property="document_number", type="string", example="1234567890"),
     *                 @OA\Property(property="company_name", type="string", example="LOPEZSOFT SAS"),
     *                 @OA\Property(property="address", type="string", example="Calle 66 # 1823"),
     *                 @OA\Property(property="phone", type="string", example="3108435431"),
     *                 @OA\Property(property="mobile", type="string", nullable=true),
     *                 @OA\Property(property="legal_representative", type="string", example="LEWIS OSWALDO LOPEZ GOMEZ"),
     *                 @OA\Property(property="legal_rep_email", type="string", nullable=true, example="juan@lopezsoft.com"),
     *                 @OA\Property(property="life", type="integer", example=1),
     *                 @OA\Property(property="postal_code", type="string", nullable=true)
     *             ),
     *             @OA\Property(property="success", type="boolean", example=true)
     *         )
     *     ),
     *     @OA\Response(response=404, description="No se encontraron solicitudes previas para ese NIT"),
     *     @OA\Response(response=401, description="No autenticado")
     * )
     */
    public function lookupByDni(string $dni): JsonResponse
    {
        return $this->service->lookupByDni($dni);
    }

    /**
     * @OA\Get(
     *     path="/certificate-request/stats/{companyId}",
     *     tags={"Solicitudes de Certificado"},
     *     summary="Estadísticas de solicitudes por año",
     *     description="Genera estadísticas de las solicitudes de certificado de una empresa agrupadas por año, con desglose por estado.",
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(name="companyId", in="path", required=true, description="ID de la empresa", @OA\Schema(type="integer")),
     *     @OA\Response(
     *         response=200,
     *         description="Estadísticas generadas",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="Estadísticas de solicitudes por año"),
     *             @OA\Property(property="dataRecords", type="object",
     *                 @OA\Property(property="company_id", type="integer", example=1),
     *                 @OA\Property(property="company_name", type="string", example="LOPEZSOFT SAS"),
     *                 @OA\Property(property="grand_total", type="integer", example=150),
     *                 @OA\Property(property="years", type="array", @OA\Items(type="object",
     *                     @OA\Property(property="year", type="integer", example=2026),
     *                     @OA\Property(property="total", type="integer", example=85),
     *                     @OA\Property(property="statuses", type="object",
     *                         @OA\Property(property="PROCESSED", type="integer", example=50),
     *                         @OA\Property(property="SENT", type="integer", example=20),
     *                         @OA\Property(property="REJECTED", type="integer", example=10),
     *                         @OA\Property(property="DRAFT", type="integer", example=5)
     *                     )
     *                 ))
     *             ),
     *             @OA\Property(property="success", type="boolean", example=true)
     *         )
     *     ),
     *     @OA\Response(response=404, description="Empresa no encontrada"),
     *     @OA\Response(response=401, description="No autenticado")
     * )
     */
    public function getStatsByCompany(int $companyId): JsonResponse
    {
        if ($companyId === 0) {
            $companyId = \App\Modules\Company\CompanyQueries::getCompany()->id;
        }

        return $this->service->getStatsByCompany($companyId);
    }
}
