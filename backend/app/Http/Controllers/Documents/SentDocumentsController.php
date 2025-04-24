<?php

namespace App\Http\Controllers\Documents;

use App\Http\Controllers\Controller;
use App\Services\ShippingQueryService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SentDocumentsController extends Controller
{

    public function getConsume(Request $request): JsonResponse
    {
        return (new ShippingQueryService())->getConsume($request);
    }
    public function getLastDocument(Request $request): JsonResponse
    {
        return (new ShippingQueryService())->getLastDocument($request);
    }
    /**
     * @throws \Exception
     */
    public function getDocuments(Request $request): JsonResponse
    {
        return (new ShippingQueryService())->getDocuments($request);
    }

    public function delete($id): JsonResponse
    {
        return (new ShippingQueryService())->delete($id);
    }
}
