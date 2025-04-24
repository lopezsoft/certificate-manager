<?php

namespace App\Http\Controllers;
use App\Modules\Settings\Resolutions;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ResolutionsController extends Controller
{
    public function create(Request $request): JsonResponse
    {
        return Resolutions::create($request);
    }
    public function getResolutions(Request $request): JsonResponse
    {
        return Resolutions::read($request);
    }
    public function update(Request $request, $id): JsonResponse
    {
       return Resolutions::update($request, $id);
    }
    public function delete(Request $request, $id): JsonResponse
    {
        return Resolutions::delete($request, $id);
    }
}
