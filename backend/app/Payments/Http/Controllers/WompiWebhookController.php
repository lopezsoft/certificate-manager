<?php

namespace App\Payments\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * WompiWebhookController — Sprint 4
 * @todo Sprint 4: implementar con ProcessWompiWebhookJob
 */
class WompiWebhookController extends Controller
{
    public function handle(Request $request): JsonResponse
    {
        // La firma ya fue validada por el middleware ValidateWompiSignature
        return response()->json(['message' => 'Webhook recibido — Implementación Sprint 4'], 200);
    }
}

