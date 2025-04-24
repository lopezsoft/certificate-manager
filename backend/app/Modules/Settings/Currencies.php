<?php

namespace App\Modules\Settings;

use App\Interfaces\CrudStaticInterface;
use App\Models\Settings\CurrencySys;
use App\Models\Types\TypeCurrency;
use App\Modules\Company\CompanyQueries;
use App\Queries\DeleteTable;
use App\Queries\InsertTable;
use App\Queries\UpdateTable;
use App\Traits\MessagesTrait;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class Currencies implements CrudStaticInterface
{
    use MessagesTrait;
    public static function create(Request $request): JsonResponse
    {
        $table      = 'currency_sys';
        $data       = array_merge(self::getRecords($request), [
            'company_id'    => CompanyQueries::getCompanyId(),
        ]);
        return InsertTable::insert($request, $data, $table);
    }

    public static function read(Request $request): JsonResponse
    {
        $uid        = $request->input('uid');
        $company_id =  $request->input('company_id') ?? CompanyQueries::getCompanyId();
        $whereSend  = ["company_id" => $company_id];

        if(isset($uid)) {
            $whereSend  = array_merge($whereSend, ["id" => $uid]);
        }
        $query  =  CurrencySys::query()->where($whereSend);
        return self::getResponse([
            'dataRecords'   => $query->paginate(),
        ]);
    }

    public static function getAll(): JsonResponse
    {
        $query  =  TypeCurrency::query()->where(['active' => 1]);
        return self::getResponse([
            'dataRecords'   => [
                'data'  => $query->get(),
            ]
        ]);
    }

    public static function update(Request $request, $id): JsonResponse
    {
        $table      = 'currency_sys';
        $data       = (object) array_merge(self::getRecords($request), [
            'id'            => $id,
            'company_id'    => CompanyQueries::getCompanyId(),
        ]);
        return UpdateTable::update($request, $data, $table);
    }

    public static function delete(Request $request, $id): JsonResponse
    {
        $table      = 'currency_sys';
        $records    = json_decode($request->input('records'));
        $records->id= $id;
        return DeleteTable::delete($request, $records, $table);
    }
    private static function getRecords(Request $request): array
    {
        $records    = json_decode($request->input('records'));
        return [
            'currency_id'           => $records->currency_id,
            'exchange_rate_value'   => $records->exchange_rate_value ?? 0,
            'national_currency'     => $records->national_currency ?? 0,
            'plural_name'           => $records->plural_name ?? null,
            'singular_name'         => $records->singular_name ?? null,
            'denomination'          => $records->denomination ?? null,
        ];
    }
}
