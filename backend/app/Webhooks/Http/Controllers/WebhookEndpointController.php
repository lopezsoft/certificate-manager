<?php

namespace App\Webhooks\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Company\CompanyQueries;
use App\Webhooks\Enums\WebhookEventType;
use App\Webhooks\Http\Requests\CreateWebhookEndpointRequest;
use App\Webhooks\Http\Requests\UpdateWebhookEndpointRequest;
use App\Webhooks\Services\WebhookService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class WebhookEndpointController extends Controller
{
    public function __construct(
        private readonly WebhookService $service,
    ) {}

    /**
     * @OA\Get(
     *     path="/webhooks/events",
     *     tags={"Webhooks"},
     *     summary="Listar tipos de evento disponibles",
     *     description="Retorna todos los tipos de evento que pueden suscribirse en un webhook.",
     *     security={{"bearerAuth":{}}},
     *     @OA\Response(
     *         response=200,
     *         description="Lista de tipos de evento",
     *         @OA\JsonContent(
     *             @OA\Property(property="data", type="array", @OA\Items(type="string"),
     *                 example={"certificate_request.created","certificate_request.status_changed","certificate_request.ai_processed","certificate_request.file_uploaded","certificate_request.deleted","certificate.expiring"}
     *             )
     *         )
     *     ),
     *     @OA\Response(response=401, description="No autenticado")
     * )
     */
    public function availableEvents(): JsonResponse
    {
        return response()->json(['data' => WebhookEventType::all()]);
    }

    /**
     * @OA\Get(
     *     path="/webhooks",
     *     tags={"Webhooks"},
     *     summary="Listar webhooks de la compañía",
     *     description="Retorna todos los endpoints webhook configurados por la compañía autenticada.",
     *     security={{"bearerAuth":{}}},
     *     @OA\Response(
     *         response=200,
     *         description="Lista de webhooks",
     *         @OA\JsonContent(
     *             @OA\Property(property="data", type="array", @OA\Items(ref="#/components/schemas/WebhookEndpoint"))
     *         )
     *     ),
     *     @OA\Response(response=401, description="No autenticado")
     * )
     */
    public function index(Request $request): JsonResponse
    {
        $endpoints = $this->service->listForCompany(CompanyQueries::getCompany()->id);

        return response()->json(['data' => $endpoints]);
    }

    /**
     * @OA\Post(
     *     path="/webhooks",
     *     tags={"Webhooks"},
     *     summary="Crear webhook",
     *     description="Registra un nuevo endpoint webhook para la compañía. Máximo 5 webhooks por compañía. El secret se genera automáticamente y solo se muestra en rotate-secret.",
     *     security={{"bearerAuth":{}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"url","events"},
     *             @OA\Property(property="url", type="string", format="uri", example="https://mi-erp.com/webhook"),
     *             @OA\Property(property="events", type="array", @OA\Items(type="string"),
     *                 example={"certificate_request.created","certificate_request.status_changed"}
     *             ),
     *             @OA\Property(property="description", type="string", nullable=true, example="Notificaciones al ERP")
     *         )
     *     ),
     *     @OA\Response(response=201, description="Webhook creado", @OA\JsonContent(@OA\Property(property="data", ref="#/components/schemas/WebhookEndpoint"))),
     *     @OA\Response(response=422, description="Validación fallida (URL inválida, evento desconocido, límite alcanzado)", @OA\JsonContent(ref="#/components/schemas/ApiErrorResponse")),
     *     @OA\Response(response=401, description="No autenticado")
     * )
     */
    public function store(CreateWebhookEndpointRequest $request): JsonResponse
    {
        $endpoint = $this->service->create(
            CompanyQueries::getCompany()->id,
            $request->validated(),
        );

        return response()->json(['data' => $endpoint], 201);
    }

    /**
     * @OA\Get(
     *     path="/webhooks/{id}",
     *     tags={"Webhooks"},
     *     summary="Obtener detalle de un webhook",
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\Response(response=200, description="Detalle del webhook", @OA\JsonContent(@OA\Property(property="data", ref="#/components/schemas/WebhookEndpoint"))),
     *     @OA\Response(response=404, description="No encontrado"),
     *     @OA\Response(response=401, description="No autenticado")
     * )
     */
    public function show(Request $request, int $id): JsonResponse
    {
        $endpoint = $this->service->findForCompany($id, CompanyQueries::getCompany()->id);

        return response()->json(['data' => $endpoint]);
    }

    /**
     * @OA\Put(
     *     path="/webhooks/{id}",
     *     tags={"Webhooks"},
     *     summary="Actualizar webhook",
     *     description="Actualiza la URL, los eventos suscritos, el estado activo/inactivo o la descripción. Todos los campos son opcionales.",
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\RequestBody(
     *         @OA\JsonContent(
     *             @OA\Property(property="url", type="string", format="uri", example="https://mi-erp.com/nuevo-webhook"),
     *             @OA\Property(property="events", type="array", @OA\Items(type="string"), example={"certificate_request.created"}),
     *             @OA\Property(property="is_active", type="boolean", example=false),
     *             @OA\Property(property="description", type="string", nullable=true, example="Descripción actualizada")
     *         )
     *     ),
     *     @OA\Response(response=200, description="Webhook actualizado", @OA\JsonContent(@OA\Property(property="data", ref="#/components/schemas/WebhookEndpoint"))),
     *     @OA\Response(response=422, description="Validación fallida", @OA\JsonContent(ref="#/components/schemas/ApiErrorResponse")),
     *     @OA\Response(response=404, description="No encontrado"),
     *     @OA\Response(response=401, description="No autenticado")
     * )
     */
    public function update(UpdateWebhookEndpointRequest $request, int $id): JsonResponse
    {
        $endpoint = $this->service->update(
            $id,
            CompanyQueries::getCompany()->id,
            $request->validated(),
        );

        return response()->json(['data' => $endpoint]);
    }

    /**
     * @OA\Delete(
     *     path="/webhooks/{id}",
     *     tags={"Webhooks"},
     *     summary="Eliminar webhook",
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\Response(response=204, description="Webhook eliminado"),
     *     @OA\Response(response=404, description="No encontrado"),
     *     @OA\Response(response=401, description="No autenticado")
     * )
     */
    public function destroy(Request $request, int $id): JsonResponse
    {
        $this->service->delete($id, CompanyQueries::getCompany()->id);

        return response()->json(null, 204);
    }

    /**
     * @OA\Post(
     *     path="/webhooks/{id}/rotate-secret",
     *     tags={"Webhooks"},
     *     summary="Rotar secret del webhook",
     *     description="Genera un nuevo secret HMAC para el webhook. El secret solo se expone en esta respuesta — única oportunidad de guardarlo. El anterior secret queda invalidado de inmediato.",
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\Response(
     *         response=200,
     *         description="Nuevo secret generado",
     *         @OA\JsonContent(
     *             @OA\Property(property="data", ref="#/components/schemas/WebhookEndpoint"),
     *             @OA\Property(property="secret", type="string", example="xK9mP2qL7nR4tY1uI8oP3aS6dF0gH5jK")
     *         )
     *     ),
     *     @OA\Response(response=404, description="No encontrado"),
     *     @OA\Response(response=401, description="No autenticado")
     * )
     */
    public function rotateSecret(Request $request, int $id): JsonResponse
    {
        $endpoint = $this->service->rotateSecret($id, CompanyQueries::getCompany()->id);

        return response()->json([
            'data'   => $endpoint,
            'secret' => $endpoint->secret,
        ]);
    }
}
