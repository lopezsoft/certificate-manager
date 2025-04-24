<?php

namespace App\Http\Controllers;

use App\Services\TableCrudService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TableCrudController extends Controller
{
    //
    /**
     * @throws \Exception
     */
    public function index(Request $request): JsonResponse
    {
        $request->uuid = null;
        return TableCrudService::read($request);
    }

    /**
     * @throws \Exception
     */
    public function store(Request $request): JsonResponse
    {
        return TableCrudService::create($request);
    }

    /**
     * @throws \Exception
     */
    public function show(Request $request, $id): JsonResponse
    {
        $request->uuid = $id;
        return TableCrudService::read($request);
    }

    /**
     * @throws \Exception
     */
    public function update(Request $request, $id): JsonResponse
    {
        return TableCrudService::update($request, $id);
    }

    /**
     * @throws \Exception
     */
    public function destroy(Request $request, $id): JsonResponse
    {
        return TableCrudService::delete($request, $id);
    }
}
