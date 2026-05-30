<?php

namespace App\Webhooks\Http\Controllers;

use App\Common\HttpResponseMessages;
use App\Common\MessageExceptionResponse;
use App\Http\Controllers\Controller;
use App\Modules\Company\CompanyQueries;
use App\Webhooks\Enums\WebhookEventType;
use App\Webhooks\Http\Requests\CreateWebhookEndpointRequest;
use App\Webhooks\Http\Requests\UpdateWebhookEndpointRequest;
use App\Webhooks\Services\WebhookService;
use Exception;
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
     *             @OA\Property(property="message", type="string", example="Tipos de evento disponibles"),
     *             @OA\Property(property="dataRecords", type="array", @OA\Items(type="string"),
     *                 example={"certificate_request.created","certificate_request.status_changed","certificate_request.ai_processed","certificate_request.file_uploaded","certificate_request.deleted","certificate.expiring","payment.approved","payment.failed"}
     *             ),
     *             @OA\Property(property="success", type="boolean", example=true)
     *         )
     *     ),
     *     @OA\Response(response=401, description="No autenticado")
     * )
     */
    public function availableEvents(): JsonResponse
    {
        return HttpResponseMessages::getResponse([
            'message'     => 'Tipos de evento disponibles',
            'dataRecords' => WebhookEventType::all(),
        ]);
    }

    /**
     * @OA\Get(
     *     path="/webhooks",
     *     tags={"Webhooks"},
     *     summary="Listar webhooks de la compañía",
     *     description="Retorna los endpoints webhook configurados por la compañía autenticada, paginados.",
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(name="limit", in="query", description="Registros por página (default: 15)", @OA\Schema(type="integer")),
     *     @OA\Response(
     *         response=200,
     *         description="Lista paginada de webhooks",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string"),
     *             @OA\Property(property="dataRecords", type="object",
     *                 @OA\Property(property="data", type="array", @OA\Items(ref="#/components/schemas/WebhookEndpoint")),
     *                 @OA\Property(property="meta", ref="#/components/schemas/PaginationMeta")
     *             ),
     *             @OA\Property(property="success", type="boolean", example=true)
     *         )
     *     ),
     *     @OA\Response(response=401, description="No autenticado")
     * )
     */
    public function index(Request $request): JsonResponse
    {
        try {
            $company   = CompanyQueries::getCompany();
            $endpoints = $this->service->listForCompany(
                $company->id,
                (int) $request->input('limit', 15),
            );

            return HttpResponseMessages::getResponse([
                'message'     => 'Lista de webhooks',
                'dataRecords' => $endpoints,
            ]);
        } catch (Exception $e) {
            return MessageExceptionResponse::response($e);
        }
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
     *             required={"name","url","events"},
     *             @OA\Property(property="name", type="string", maxLength=100, example="Notificaciones ERP"),
     *             @OA\Property(property="url", type="string", format="uri", example="https://mi-erp.com/webhook"),
     *             @OA\Property(property="events", type="array", @OA\Items(type="string"),
     *                 example={"certificate_request.created","certificate_request.status_changed"}
     *             ),
     *             @OA\Property(property="description", type="string", nullable=true, example="Notificaciones al ERP")
     *         )
     *     ),
     *     @OA\Response(response=201, description="Webhook creado",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string"),
     *             @OA\Property(property="dataRecords", ref="#/components/schemas/WebhookEndpoint"),
     *             @OA\Property(property="success", type="boolean", example=true)
     *         )
     *     ),
     *     @OA\Response(response=422, description="Validación fallida"),
     *     @OA\Response(response=401, description="No autenticado")
     * )
     */
    public function store(CreateWebhookEndpointRequest $request): JsonResponse
    {
        try {
            $company  = CompanyQueries::getCompany();
            $endpoint = $this->service->create($company->id, $request->validated());

            return HttpResponseMessages::getResponse201([
                'message'     => 'Webhook creado exitosamente',
                'dataRecords' => $endpoint,
            ]);
        } catch (Exception $e) {
            return MessageExceptionResponse::response($e);
        }
    }

    /**
     * @OA\Get(
     *     path="/webhooks/{id}",
     *     tags={"Webhooks"},
     *     summary="Obtener detalle de un webhook",
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\Response(response=200, description="Detalle del webhook",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string"),
     *             @OA\Property(property="dataRecords", ref="#/components/schemas/WebhookEndpoint"),
     *             @OA\Property(property="success", type="boolean", example=true)
     *         )
     *     ),
     *     @OA\Response(response=404, description="No encontrado"),
     *     @OA\Response(response=401, description="No autenticado")
     * )
     */
    public function show(Request $request, int $id): JsonResponse
    {
        try {
            $company  = CompanyQueries::getCompany();
            $endpoint = $this->service->findForCompany($id, $company->id);

            return HttpResponseMessages::getResponse([
                'message'     => 'Detalle del webhook',
                'dataRecords' => $endpoint,
            ]);
        } catch (Exception $e) {
            return MessageExceptionResponse::response($e);
        }
    }

    /**
     * @OA\Put(
     *     path="/webhooks/{id}",
     *     tags={"Webhooks"},
     *     summary="Actualizar webhook",
     *     description="Actualiza nombre, URL, eventos, estado o descripción. Todos los campos son opcionales.",
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\RequestBody(
     *         @OA\JsonContent(
     *             @OA\Property(property="name", type="string", maxLength=100, example="Nombre actualizado"),
     *             @OA\Property(property="url", type="string", format="uri", example="https://mi-erp.com/nuevo-webhook"),
     *             @OA\Property(property="events", type="array", @OA\Items(type="string"), example={"certificate_request.created"}),
     *             @OA\Property(property="is_active", type="boolean", example=false),
     *             @OA\Property(property="description", type="string", nullable=true, example="Descripción actualizada")
     *         )
     *     ),
     *     @OA\Response(response=200, description="Webhook actualizado",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string"),
     *             @OA\Property(property="dataRecords", ref="#/components/schemas/WebhookEndpoint"),
     *             @OA\Property(property="success", type="boolean", example=true)
     *         )
     *     ),
     *     @OA\Response(response=422, description="Validación fallida"),
     *     @OA\Response(response=404, description="No encontrado"),
     *     @OA\Response(response=401, description="No autenticado")
     * )
     */
    public function update(UpdateWebhookEndpointRequest $request, int $id): JsonResponse
    {
        try {
            $company  = CompanyQueries::getCompany();
            $endpoint = $this->service->update($id, $company->id, $request->validated());

            return HttpResponseMessages::getResponse([
                'message'     => 'Webhook actualizado exitosamente',
                'dataRecords' => $endpoint,
            ]);
        } catch (Exception $e) {
            return MessageExceptionResponse::response($e);
        }
    }

    /**
     * @OA\Delete(
     *     path="/webhooks/{id}",
     *     tags={"Webhooks"},
     *     summary="Eliminar webhook",
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\Response(response=200, description="Webhook eliminado",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string"),
     *             @OA\Property(property="success", type="boolean", example=true)
     *         )
     *     ),
     *     @OA\Response(response=404, description="No encontrado"),
     *     @OA\Response(response=401, description="No autenticado")
     * )
     */
    public function destroy(Request $request, int $id): JsonResponse
    {
        try {
            $company = CompanyQueries::getCompany();
            $this->service->delete($id, $company->id);

            return HttpResponseMessages::getResponse([
                'message' => 'Webhook eliminado exitosamente',
            ]);
        } catch (Exception $e) {
            return MessageExceptionResponse::response($e);
        }
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
     *             @OA\Property(property="message", type="string"),
     *             @OA\Property(property="dataRecords", type="object",
     *                 @OA\Property(property="endpoint", ref="#/components/schemas/WebhookEndpoint"),
     *                 @OA\Property(property="secret", type="string", example="xK9mP2qL7nR4tY1uI8oP3aS6dF0gH5jK")
     *             ),
     *             @OA\Property(property="success", type="boolean", example=true)
     *         )
     *     ),
     *     @OA\Response(response=404, description="No encontrado"),
     *     @OA\Response(response=401, description="No autenticado")
     * )
     */
    public function rotateSecret(Request $request, int $id): JsonResponse
    {
        try {
            $company  = CompanyQueries::getCompany();
            $endpoint = $this->service->rotateSecret($id, $company->id);

            return HttpResponseMessages::getResponse([
                'message'     => 'Secret rotado exitosamente',
                'dataRecords' => [
                    'endpoint' => $endpoint,
                    'secret'   => $endpoint->secret,
                ],
            ]);
        } catch (Exception $e) {
            return MessageExceptionResponse::response($e);
        }
    }
}
