<?php

namespace App\Services;

use App\Common\HttpResponseMessages;
use App\Common\MessageExceptionResponse;
use App\Modules\Company\CompanyQueries;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class GeneraSettingsService
{
    public static function updateSetting(Request $request): \Illuminate\Http\JsonResponse
    {
        try {
            $company    = CompanyQueries::getCompany();
            $records    = json_decode($request->input('records'));
            foreach ($records as $value) {
                DB::table('general_setting_companies')
                    ->where('id', $value->id)
                    ->limit(1)
                    ->update(['value' => $value->value]);
            }
            return HttpResponseMessages::getResponse([
                'message'   => 'Configuración actualizada',
            ]);
        }catch (\Exception $e) {
            return MessageExceptionResponse::response($e);
        }
    }
}
