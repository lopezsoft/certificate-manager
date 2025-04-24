<?php

namespace App\Http\Controllers\Mail;

use App\Http\Controllers\Controller;
use App\Services\Mail\EmailSendingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class EmailController extends Controller
{
    public function getEmail(Request $request): JsonResponse
    {
        return EmailSendingService::getSMTP($request);
    }

    public function postEmail(Request $request): JsonResponse
    {
        return EmailSendingService::createSMTP($request);
    }

    public function putEmail(Request $request): JsonResponse
    {
        return EmailSendingService::updateSMTP($request);
    }

    public function testEmail(Request $request): JsonResponse
    {
        return EmailSendingService::sendTestSMTP($request);
    }

}
