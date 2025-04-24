<?php

namespace App\Modules\Settings;
use App\Common\HttpResponseMessages;
use App\Interfaces\CrudStaticInterface;
use App\Models\Settings\Resolution;
use App\Modules\Company\CompanyQueries;
use App\Queries\InsertTable;
use App\Queries\UpdateTable;
use App\Traits\MessagesTrait;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class Resolutions implements CrudStaticInterface {
    use MessagesTrait;
    public static function create(Request $request): JsonResponse
    {
        $table      = 'resolutions';
        $records    = json_decode($request->input('records'));
        $company_id = $request->input('companyId') ?? CompanyQueries::getCompanyId();
        $data       = (object) array_merge(['company_id' => $company_id], self::getRecordData($records));
        return InsertTable::insert($request, $data, $table);
    }
    public static function read(Request $request): JsonResponse
    {
        try {
            $uid        = $request->input('uid');
            $active     = $request->active ?? null;
            $company_id = $request->input('companyId') ?? CompanyQueries::getCompanyId();
            $whereSend  = [
                'company_id'    => $company_id
            ];
            if(isset($uid)){
                $whereSend['id']  = $uid;
            }
            $query  = Resolution::query()->where($whereSend);
            if(!is_null($active)) {
                $query->where('active', $active);
            }
            return  self::getResponse([
                'dataRecords'   => $query->paginate(),
            ]);
        } catch (\Exception $e) {
            return HttpResponseMessages::getResponse500();
        }
    }

    public static function update(Request $request, $id): JsonResponse
    {
        $records    = json_decode($request->input('records'));
        $data       = array_merge(['id' => $id], self::getRecordData($records));
        $resolutions= Resolution::query()->where('id', $id)->first();
        $resolutions->updateOnlyChanged($request, $data);
        return HttpResponseMessages::getResponse();
    }

    public static function delete(Request $request, $id): JsonResponse
    {
        $table      = 'resolutions';
        $data    = (object) [
            'id'                => $id,
            'active'            => '0',
        ];
        return UpdateTable::update($request, $data, $table);
    }

    private static function getRecordData($records): array
    {
        return [
            'type_document_id'  => $records->type_document_id,
            'headerline1'       => $records->headerline1 ?? '',
            'headerline2'       => $records->headerline2 ?? '',
            'footline1'         => $records->footline1 ?? '',
            'footline2'         => $records->footline2 ?? '',
            'footline3'         => $records->footline3 ?? '',
            'footline4'         => $records->footline4 ?? '',
            'initial_number'    => $records->initial_number ?? 1,
            'prefix'            => trim($records->prefix) ?? '',
            'invoice_name'      => $records->invoice_name,
            'range_from'        => $records->range_from,
            'range_up'          => $records->range_up,
            'date_from'         => date('Y-m-d',strtotime($records->date_from)),
            'date_up'           => date('Y-m-d',strtotime($records->date_up)),
            'resolution_number' => trim($records->resolution_number) ?? '',
            'active'            => $records->active ?? 1,
            'technical_key'     => trim($records->technical_key) ?? '',
        ];
    }

}
