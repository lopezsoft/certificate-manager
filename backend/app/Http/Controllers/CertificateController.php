<?php

namespace App\Http\Controllers;

use App\Modules\Settings\CertificateService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CertificateController extends Controller
{
    public function create(Request $request): JsonResponse
    {
        return CertificateService::create($request);
    }

    /**
     * @throws \Exception
     */
    public function getExpiration($dni): JsonResponse
    {
        return CertificateService::expiration($dni);
    }

    /**
     * @throws \Exception
     */
    public function getCertificate(Request $request): JsonResponse
    {
        return CertificateService::read($request);
    }

    public function update(Request $request, $id): JsonResponse
    {
        return CertificateService::update($request, $id);
    }

    public function delete(Request $request, $id): JsonResponse
    {
        return CertificateService::delete($request, $id);
    }
}
