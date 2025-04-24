<?php

namespace App\Models\Company;

use App\Common\HttpResponseMessages;
use App\Common\MessageExceptionResponse;
use App\Common\VerificationDigit;
use App\Models\Company;
use App\Modules\Company\CompanyQueries;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class CompanyModel
{

    /**
     * Delete customer
     * @throws Exception
     */
    public static function deleteCustomer($id): JsonResponse
    {
        try {
            $company    = CompanyQueries::getCompany();
            $customer   = Company::query()->where('id', $id)->first();
            if(!$customer) {
                throw new Exception('El cliente no existe.', 404);
            }
            DB::table('auxiliary_companies')
                ->where('company_id', $company->id)
                ->where('customer_id', $customer->id)
                ->limit(1)
                ->update([
                    'active'    => 0
                ]);
            return HttpResponseMessages::getResponse([
                'message'   => 'Cliente eliminado correctamente.'
            ]);
        } catch (Exception $e) {
            return MessageExceptionResponse::response($e);
        }
    }

    /**
     * @throws Exception
     */
    public static function customers(Request $request): JsonResponse
    {
        $company    = CompanyQueries::getCompany();
        $query      = Company::select('companies.*', 'b.company_id')
                        ->join('auxiliary_companies AS b', 'b.customer_id', '=', 'companies.id')
                        ->where('b.company_id', $company->id)
                        ->where('b.active', 1);
        $search     = $request->input('query');
        if($search) {
            $query  = $query->where('companies.dni', 'like', "%$search%")
                            ->orWhere('companies.company_name', 'like', "%$search%");
        }
        return HttpResponseMessages::getResponse([
            'dataRecords'           => $query->paginate()
        ]);
    }

    /**
     * @throws Exception
     */
    public static function read(Request $request): JsonResponse
    {
        try {
            $uid    = $request->input('uid');
            if($uid) {
                $company    = Company::where('id', $uid)->first();
            } else {
                $company    = CompanyQueries::getCompany();
            }
            return HttpResponseMessages::getResponse([
                'dataRecords'   => [
                    'data' => [$company]
                ],
                'total'     => 1,
            ]);
        }catch (Exception $e) {
            return MessageExceptionResponse::response($e);
        }

    }

    public static function update(Request $request, $id): JsonResponse
    {
        try {
            $records        = json_decode($request->input('records'));
            $company        = Company::query()->where('id', $id)->first();

            if (!$company) {
                throw new Exception('La empresa no existe.', 404);
            }

            if (!$records) {
                throw new Exception('La propiedad RECORDS no está definida.');
            }

            if ($records->country_id  === 45) {
                $dv             = VerificationDigit::getDigit($records->dni);
            }
            $records->dv    = $dv ?? $company->dv;

            if (isset($records->imgdata)) {
                //get the base-64 from data
                $base64_str = substr($records->imgdata, strpos($records->imgdata, ",") + 1);

                if (!empty($base64_str)) {
                    //decode base64 string
                    $image              = base64_decode($base64_str);
                    $imgName            = $records->imgname;
                    $records->image    = self::putFile($company->id, $image, $imgName);
                }
            }
            $company->updateOnlyChanged($request, (array) $records);
            return HttpResponseMessages::getResponse([
                'dataRecords'   => $company,
                'message'       => 'Registro actualizado correctamente.'
            ]);
        } catch (Exception $e) {
            return MessageExceptionResponse::response($e);
        }
    }
    private static function putFile($company_id, $data, $imgName): string
    {
        $extension      = pathinfo($imgName, PATHINFO_EXTENSION);
        $imageName      = Str::uuid() . '.' . $extension;
        $path           = "/companies/{$company_id}/logo/" . $imageName;
        Storage::disk('public')->put($path, $data);
        return "/storage{$path}";
    }
}
