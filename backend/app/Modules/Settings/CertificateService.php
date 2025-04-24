<?php

namespace App\Modules\Settings;

use App\Common\HttpResponseMessages;
use App\Common\MessageExceptionResponse;
use App\Interfaces\CrudStaticInterface;
use App\Models\Settings\Certificate;
use App\Modules\Company\CompanyQueries;
use App\Queries\AuditTable;
use App\Queries\DeleteTable;
use App\Services\Certificate\CertificateValidatorService;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CertificateService implements CrudStaticInterface
{
    public static function create(Request $request): JsonResponse
    {
        try {
            $records        = json_decode($request->input('records'));
            $company        = CompanyQueries::getCompanyByRequest($request);
            $exists         = Certificate::query()->where('company_id', $company->id)->first();
            if($exists){
                throw new Exception('Ya existe un certificado para esta empresa.');
            }
            if (!isset($records->data)) {
                throw new Exception('El certificado es requerido.');
            }
            $password       = $records->password;
            $data           = substr($records->data, strpos($records->data, ",") + 1);
            $records->data  = $data;
            $params         = (object) [
                'password'  => $password,
                'data'      => $data,
                'company'   => $company
            ];
            $certificateData= CertificateValidatorService::validate($params);
            $record       = [
                'company_id'        => $company->id,
                'name'              => $certificateData->name,
                'password'          => $records->password,
                'data'              => $data,
                'description'       => $records->description,
                'extension'         =>'.p12',
                'expiration_date'   => $certificateData->expirationDate
            ];
            $table      = 'certificates';
            $certificate= Certificate::create($record);
            AuditTable::audit($request->ip(), $table, 'create', $data);
            return HttpResponseMessages::getResponse([
                'dataRecords'   => [
                    'data'  => $certificate,
                ],
            ]);
        } catch (Exception $e) {
            return MessageExceptionResponse::response($e);
        }
    }

    /**
     * @throws Exception
     */
    public static function read(Request $request): JsonResponse
    {
        $company    = CompanyQueries::getCompany();
        $whereSend  = [
            'company_id'    => $request->input('companyId') ?? $company->id
        ];
        return HttpResponseMessages::getResponse([
            'dataRecords'   => Certificate::query()->where($whereSend)->paginate(),
        ]);
    }

    public static function update(Request $request, $id): JsonResponse
    {
        try {
            $records        = json_decode($request->input('records'));
            $company        = CompanyQueries::getCompanyByRequest($request);
            $certificate    = Certificate::query()->where('company_id', $company->id)->first();
            if(!$certificate){
                throw new Exception('No existe un certificado para esta empresa.');
            }
            if (!isset($records->data)) {
                throw new Exception('El contenido del certificado es requerido.');
            }
            $ext    = strpos($records->data, ",");
            if (!$ext) {
                throw new Exception('El certificado es requerido.');
            }
            $password       = $records->password ?? $certificate->password;
            $data           = substr($records->data, strpos($records->data, ",") + 1);
            $records->data  = $data;
            $params         = (object) [
                'password'  => $password,
                'data'      => $data,
                'company'   => $company
            ];
            $certificateData= CertificateValidatorService::validate($params);
            $record       = [
                'name'              => $certificateData->name,
                'password'          => $records->password,
                'data'              => $records->data,
                'description'       => $records->description,
                'extension'         =>'.p12',
                'expiration_date'   => $certificateData->expirationDate
            ];
            $certificate->updateOnlyChanged($request, $record);
            $certificate->refresh();
            return HttpResponseMessages::getResponse([
                'dataRecords'   => [
                    'data'  => $certificate,
                ],
            ]);
        } catch (Exception $e) {
            return MessageExceptionResponse::response($e);
        }
    }

    public static function delete(Request $request, $id): JsonResponse
    {
        $table      = 'certificates';
        $records    = json_decode($request->input('records'));
        $records->id= $id;
        return DeleteTable::delete($request, $records, $table);
    }

    /**
     * @throws Exception
     */
    public static function expiration($dni): JsonResponse
    {
        return HttpResponseMessages::getResponse([
            'dataRecords'   => CertificateValidatorService::extractExpiration($dni),
        ]);
    }

}
