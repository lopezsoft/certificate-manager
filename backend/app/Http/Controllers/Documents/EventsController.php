<?php

namespace App\Http\Controllers\Documents;

use App\Http\Controllers\Controller;
use App\Services\Events\DocumentReceptionService;
use App\Services\Events\EventMasterService;
use App\Services\Events\EventSendMailService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class EventsController extends Controller
{
    public function sendEventMail($trackId): JsonResponse
    {
        return (new EventSendMailService())->send($trackId);
    }
    public function sendEvent(Request $request, $trackId): JsonResponse
    {
        return (new EventMasterService())->create($request, $trackId);
    }
    public function getDocumentReceptions(Request $request): JsonResponse
    {
        return (new DocumentReceptionService())->getDocumentReceptions($request);
    }
    public function getEventSById(Request $request, $documentId): JsonResponse
    {
        return (new DocumentReceptionService())->getEventSById($request, $documentId);
    }
    public function getEventStatus($trackId): JsonResponse
    {
        return (new DocumentReceptionService())->getEventStatus($trackId);
    }
    public function importExcel(Request $request): JsonResponse
    {
        return (new DocumentReceptionService())->importExcel($request);
    }

    public function importTrackId(Request $request): JsonResponse
    {
        return (new DocumentReceptionService())->importTrackId($request);
    }

    public function importByTrackId(Request $request, $trackId): JsonResponse
    {
        $request->merge(['trackId' => $trackId]);
        return (new DocumentReceptionService())->importTrackId($request);
    }
}
