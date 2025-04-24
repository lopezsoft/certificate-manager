<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Settings\ReportsHeader;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ReportsHeaderController extends Controller
{
    /**
     * @throws \Exception
     */
    public function getData(Request $request): JsonResponse
    {
        return (new ReportsHeader())->getData($request);
    }

    public function update(Request $request, $id): JsonResponse
    {
        return (new ReportsHeader())->update($request, $id);
    }
}
