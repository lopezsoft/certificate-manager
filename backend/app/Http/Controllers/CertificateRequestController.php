<?php

namespace App\Http\Controllers;

use App\Http\Requests\Certificate\CreateCertificateRequestFormRequest;
use App\Http\Requests\Certificate\UpdateCertificateRequestFormRequest;
use App\Services\CertificateRequestMailService;
use App\Services\CertificateRequestService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CertificateRequestController extends Controller
{
    public function __construct(
        private readonly CertificateRequestService $service,
        private readonly CertificateRequestMailService $mailService
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
     *                 @OA\Property(property="document_number", type="string", example="1234567890"),
     *                 @OA\Property(property="address", type="string", example="Calle 123 # 45-67"),
     *                 @OA\Property(property="legal_representative", type="string", example="JUAN PÉREZ"),
     *                 @OA\Property(property="company_name", type="string", example="MI EMPRESA S.A.S."),
     *                 @OA\Property(property="dni", type="string", example="900455420"),
     *                 @OA\Property(property="life", type="integer", example=1),
     *                 @OA\Property(property="info", type="string", nullable=true)
     *             )
     *         )
     *     ),
     *     @OA\Response(response=200, description="Solicitud creada exitosamente", @OA\JsonContent(ref="#/components/schemas/ApiSuccessResponse")),
     *     @OA\Response(response=400, description="Validación fallida o solicitud duplicada", @OA\JsonContent(ref="#/components/schemas/ApiErrorResponse")),
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
     *     @OA\Response(response=200, description="Lista paginada de solicitudes"),
     *     @OA\Response(response=401, description="No autenticado")
     * )
     */
    public function getCertificateRequest(Request $request): JsonResponse
    {
        return $this->service->getCertificateRequest($request);
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
     *     @OA\Response(response=200, description="Lista de solicitudes"),
     *     @OA\Response(response=401, description="No autenticado")
     * )
     */
    public function getAllCertificateRequest(Request $request): JsonResponse
    {
        return $this->service->getAllCertificateRequest($request);
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
    public function getCertificateRequestById($id): JsonResponse
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
     *     @OA\Response(response=200, description="Solicitud actualizada"),
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
     *     @OA\Response(response=200, description="Estado actualizado"),
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
     *     @OA\Response(response=200, description="Solicitud eliminada"),
     *     @OA\Response(response=401, description="No autenticado")
     * )
     */
    public function deleteCertificateRequest($id): JsonResponse
    {
        return $this->service->deleteCertificateRequest($id);
    }

    /**
     * @OA\Post(
     *     path="/certificate-request/{id}/send-mail",
     *     tags={"Solicitudes de Certificado"},
     *     summary="Enviar solicitud por correo electrónico",
     *     description="Envía la solicitud al proveedor y actualiza el estado a PROCESSING. Límite: 5 envíos/minuto.",
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\RequestBody(
     *         @OA\JsonContent(
     *             schema="SendMailBody",
     *             @OA\Property(property="comments", type="string", nullable=true)
     *         )
     *     ),
     *     @OA\Response(response=200, description="Correo enviado exitosamente"),
     *     @OA\Response(response=400, description="Solicitud no encontrada o sin archivos adjuntos"),
     *     @OA\Response(response=429, description="Demasiados envíos"),
     *     @OA\Response(response=401, description="No autenticado")
     * )
     */
    public function sendMail(Request $request, $id): JsonResponse
    {
        return $this->mailService->sendMail($request, $id);
    }
}
