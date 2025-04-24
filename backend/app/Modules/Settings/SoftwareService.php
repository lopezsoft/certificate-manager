<?php
namespace App\Modules\Settings;
use App\Common\HttpResponseMessages;
use App\Common\MessageExceptionResponse;
use App\Interfaces\CrudStaticInterface;
use App\Models\Test\TestDocument;
use App\Modules\Company\CompanyQueries;
use App\Queries\DeleteTable;
use App\Queries\InsertTable;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use App\Models\Settings\Software;
class SoftwareService implements CrudStaticInterface
{
    public static function create(Request $request): JsonResponse
    {
        $company_id = $request->input('companyId') ?? CompanyQueries::getCompanyId();
        if($company_id > 0){
            $table      = 'software_information';
            $records    = json_decode($request->input('records'));
            $data       = array_merge([
                'company_id'                => $company_id,
                'url'                       => $records->url ?? 'https://vpfe-hab.dian.gov.co/WcfDianCustomerServices.svc',
            ], self::getSoftwareData($request));
            return InsertTable::insert($request, $data,$table);
        } else{
            return HttpResponseMessages::getResponse([
                'message'       => 'No se encontró la compañía.',
            ]);
        }
    }
    public static function read(Request $request): JsonResponse
    {
        $uid        = $request->input('uid') ?? $request->input('id');
        if(isset($uid)){
            $whereSend  = array(
                'id'    => $uid
            );
        }else{
            $whereSend  = array(
                'company_id'    => $request->input('companyId') ?? CompanyQueries::getCompanyId()
            );
        }
        $query  = Software::query()->where($whereSend)->with(['testProcess']);
        return HttpResponseMessages::getResponse([
            'dataRecords'   => $query->paginate(),
        ]);
    }
    public static function update(Request $request, $id): JsonResponse
    {
        try {
            $data       = json_decode($request->input('records') ?? [], true, 512, JSON_THROW_ON_ERROR);
            $software   = Software::query()->find($id);
            if(!$software){
                throw new Exception('Software not found', 404);
            }
            $software->updateOnlyChanged($request, $data);
            return HttpResponseMessages::getResponse([
                'message'       => 'Software actualizado correctamente.',
                'dataRecords'   => $software,
            ]);
        }catch (Exception $e){
            return MessageExceptionResponse::response($e);
        }
    }
    public static function delete(Request $request, $id): JsonResponse
    {
        $table      = 'software_information';
        $records    = json_decode($request->input('records'));
        return DeleteTable::delete($request, $records, $table);
    }
    public static function getSoftwareData(Request $request): array
    {
        $records    = json_decode($request->input('records'));
        return [
            'environment_id'            => $records->environment_id ?? 2,
            'type_id'                   => $records->type_id ?? 1,
            'integration_type'          => $records->integration_type ?? 1,
            'account_id'                => $records->account_id ?? null,
            'auth_token'                => $records->auth_token ?? null,
            'testsetid'                 => $records->testsetid ?? null,
            'technical_key'             => $records->technical_key ?? "fc8eac422eba16e22ffd8c6f94b3f40a6e38162c",
            'pin'                       => $records->pin,
            'identification'            => $records->identification ?? null,
            'initial_number'            => $records->initial_number ?? 1,
            'test_process_status'       => 'INITIAL',
        ];
    }

    public static function test($id): JsonResponse
    {
        try {
            $software   = TestDocument::query()
                ->where('software_id', $id)
                ->orderBy('id', 'DESC')
                ->with(['software', 'process', 'document']);
            return HttpResponseMessages::getResponse([
                'dataRecords'  => $software->paginate()
            ]);
        }catch (Exception $e){
            return MessageExceptionResponse::response($e);
        }
    }
    public static function process($id): JsonResponse
    {
        try {
            $software   = TestDocument::query()
                ->where('process_id', $id)
                ->orderBy('id', 'DESC')
                ->with(['software', 'process', 'document']);
            return HttpResponseMessages::getResponse([
                'dataRecords'  => $software->paginate()
            ]);
        }catch (Exception $e){
            return MessageExceptionResponse::response($e);
        }
    }
}
