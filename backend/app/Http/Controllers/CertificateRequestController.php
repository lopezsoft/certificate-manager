<?php

namespace App\Http\Controllers;

use App\Services\CertificateRequestMailService;
use App\Services\CertificateRequestService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CertificateRequestController extends Controller
{
    public function createCertificateRequest(Request $request): JsonResponse
    {
        return (new CertificateRequestService())->createCertificateRequest($request);
    }

    public function getCertificateRequest(Request $request): JsonResponse
    {
        return (new CertificateRequestService())->getCertificateRequest($request);
    }

    public function getAllCertificateRequest(Request $request): JsonResponse
    {
        return (new CertificateRequestService())->getAllCertificateRequest($request);
    }

    public function getCertificateRequestById($id): JsonResponse
    {
        return (new CertificateRequestService())->getCertificateRequestById($id);
    }

    public function updateCertificateRequest(Request $request, $id): JsonResponse
    {
        return (new CertificateRequestService())->updateCertificateRequest($request, $id);
    }
    public function updateCertificateRequestStatus(Request $request, $id): JsonResponse
    {
        return (new CertificateRequestService())->updateCertificateRequestStatus($request, $id);
    }

    public function deleteCertificateRequest($id): JsonResponse
    {
        return (new CertificateRequestService())->deleteCertificateRequest($id);
    }
    public function sendMail(Request $request, $id): JsonResponse
    {
        return (new CertificateRequestMailService())->sendMail($request, $id);
    }
}
