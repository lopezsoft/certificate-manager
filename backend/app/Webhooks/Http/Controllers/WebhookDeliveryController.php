<?php

namespace App\Webhooks\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Webhooks\Models\WebhookDelivery;
use App\Webhooks\Services\WebhookService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class WebhookDeliveryController extends Controller
{
    public function __construct(
        private readonly WebhookService $service,
    ) {}

    public function index(Request $request, int $endpointId): JsonResponse
    {
        $endpoint = $this->service->findForCompany($endpointId, $request->user()->company_id);

        $deliveries = WebhookDelivery::query()
            ->where('webhook_endpoint_id', $endpoint->id)
            ->orderByDesc('created_at')
            ->paginate($request->input('limit', 20));

        return response()->json(['data' => $deliveries]);
    }
}
