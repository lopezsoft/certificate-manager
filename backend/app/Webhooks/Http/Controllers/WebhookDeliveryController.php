<?php

namespace App\Webhooks\Http\Controllers;

use App\Common\HttpResponseMessages;
use App\Common\MessageExceptionResponse;
use App\Http\Controllers\Controller;
use App\Modules\Company\CompanyQueries;
use App\Webhooks\Models\WebhookDelivery;
use App\Webhooks\Services\WebhookService;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class WebhookDeliveryController extends Controller
{
    public function __construct(
        private readonly WebhookService $service,
    ) {}

    /**
     * @OA\Get(
     *     path="/webhooks/{id}/deliveries",
     *     tags={"Webhooks"},
     *     summary="Historial de entregas de un webhook",
     *     description="Retorna el historial paginado de todos los intentos de entrega para un endpoint webhook específico.",
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(name="id", in="path", required=true, description="ID del webhook endpoint", @OA\Schema(type="integer")),
     *     @OA\Parameter(name="limit", in="query", description="Registros por página (default: 20)", @OA\Schema(type="integer", example=20)),
     *     @OA\Response(
     *         response=200,
     *         description="Historial paginado de entregas",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string"),
     *             @OA\Property(property="dataRecords", type="object",
     *                 @OA\Property(property="data", type="array", @OA\Items(ref="#/components/schemas/WebhookDelivery")),
     *                 @OA\Property(property="meta", ref="#/components/schemas/PaginationMeta")
     *             ),
     *             @OA\Property(property="success", type="boolean", example=true)
     *         )
     *     ),
     *     @OA\Response(response=404, description="Webhook no encontrado"),
     *     @OA\Response(response=401, description="No autenticado")
     * )
     */
    public function index(Request $request, int $endpointId): JsonResponse
    {
        try {
            $company  = CompanyQueries::getCompany();
            $endpoint = $this->service->findForCompany($endpointId, $company->id);

            $deliveries = WebhookDelivery::query()
                ->where('webhook_endpoint_id', $endpoint->id)
                ->orderByDesc('created_at')
                ->paginate($request->input('limit', 20));

            return HttpResponseMessages::getResponse([
                'message'     => 'Historial de entregas del webhook',
                'dataRecords' => $deliveries,
            ]);
        } catch (Exception $e) {
            return MessageExceptionResponse::response($e);
        }
    }
}
