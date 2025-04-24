<?php

namespace App\Http\Controllers;

use App\Interfaces\CrudInterface;
use App\Modules\Settings\Currencies;
use App\Modules\Settings\CurrencyChangeLocal;
use App\Traits\MessagesTrait;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CurrencyController extends Controller implements CrudInterface
{
    use MessagesTrait;
    private string $APIKEY    = "2097|tbf^pf2THM1bJ*MDFH^U_WTp1TWuMaa8";

    public function create(Request $request): JsonResponse
    {
        return Currencies::create($request);
    }
    public function read(Request $request): JsonResponse
    {
        return Currencies::read($request);
    }
    public function all(): JsonResponse
    {
        return Currencies::getAll();
    }
    public function update(Request $request, $id): JsonResponse
    {
        return Currencies::update($request, $id);
    }
    public function delete(Request $request, $id): JsonResponse
    {
        return Currencies::delete($request, $id);
    }

    public function getChangeLocal(Request $request): JsonResponse
    {
        return (new CurrencyChangeLocal())->getChangeLocal($request);
    }

    public function getChange(Request $request): JsonResponse
    {
        return (new CurrencyChangeLocal())->getChange($request);
    }

}
