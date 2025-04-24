<?php

namespace App\Http\Controllers;

use App\Modules\Settings\SoftwareService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SoftwareController extends Controller
{
    public function create(Request $request): JsonResponse
    {
       return SoftwareService::create($request);
    }
    public function getProcessSoftware($id): JsonResponse
    {
        return SoftwareService::process($id);
    }
    public function getTestSoftware($id): JsonResponse
    {
        return SoftwareService::test($id);
    }
    public function getSoftware(Request $request): JsonResponse
    {
        return SoftwareService::read($request);
    }

    public function update(Request $request, $id): JsonResponse
    {
        return SoftwareService::update($request, $id);
    }

    public function delete(Request $request, $id): JsonResponse
    {
        return SoftwareService::delete($request, $id);
    }
}
