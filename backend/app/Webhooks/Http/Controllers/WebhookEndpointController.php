<?php

namespace App\Webhooks\Http\Controllers;

use App\Http\Controllers\Controller;
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

    public function index(Request $request): JsonResponse
    {
        $endpoints = $this->service->listForCompany($request->user()->company_id);

        return response()->json(['data' => $endpoints]);
    }

    public function store(CreateWebhookEndpointRequest $request): JsonResponse
    {
        $endpoint = $this->service->create(
            $request->user()->company_id,
            $request->validated(),
        );

        return response()->json(['data' => $endpoint], 201);
    }

    public function show(Request $request, int $id): JsonResponse
    {
        $endpoint = $this->service->findForCompany($id, $request->user()->company_id);

        return response()->json(['data' => $endpoint]);
    }

    public function update(UpdateWebhookEndpointRequest $request, int $id): JsonResponse
    {
        $endpoint = $this->service->update(
            $id,
            $request->user()->company_id,
            $request->validated(),
        );

        return response()->json(['data' => $endpoint]);
    }

    public function destroy(Request $request, int $id): JsonResponse
    {
        $this->service->delete($id, $request->user()->company_id);

        return response()->json(null, 204);
    }

    public function rotateSecret(Request $request, int $id): JsonResponse
    {
        $endpoint = $this->service->rotateSecret($id, $request->user()->company_id);

        return response()->json([
            'data'   => $endpoint,
            'secret' => $endpoint->secret,
        ]);
    }

    public function availableEvents(): JsonResponse
    {
        return response()->json(['data' => WebhookEventType::all()]);
    }
}
