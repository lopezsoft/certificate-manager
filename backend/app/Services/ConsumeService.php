<?php

namespace App\Services;

use App\Common\HttpResponseMessages;
use App\Common\MessageExceptionResponse;
use App\Enums\CertificateRequestStatusEnum;
use App\Modules\Company\CompanyQueries;
use Exception;
use Illuminate\Support\Facades\DB;

class ConsumeService
{
    public function readByYear($year): \Illuminate\Http\JsonResponse
    {
        try {
            $user           = auth()->user();
            $company        = CompanyQueries::getCompany();
            $companyId      = $company->id;
            $query          = DB::table('certificate_requests_years_view')
                                ->where('nyear', $year)
                                ->whereIn('request_status', CertificateRequestStatusEnum::issuedStatuses());
            if($user->type_id !== 1){
                $query->where('company_id', $companyId);
            };
            return HttpResponseMessages::getResponse([
                'data' => $query->get(),
                'message' => 'Data retrieved successfully',
            ]);
        }catch (Exception $e) {
            return MessageExceptionResponse::response($e);
        }
    }

    public function readByMonth($year, $month): \Illuminate\Http\JsonResponse
    {
        try {
            $user           = auth()->user();
            $company        = CompanyQueries::getCompany();
            $companyId      = $company->id;
            $query          = DB::table('certificate_requests_months_view')
                                ->where('nyear', $year)
                                ->whereIn('request_status', CertificateRequestStatusEnum::issuedStatuses());
            if($month > 0){
                $query->where('nmonth', $month);
            }
            if($user->type_id !== 1){
                $query->where('company_id', $companyId);
            };
            return HttpResponseMessages::getResponse([
                'data' => $query->get(),
                'message' => 'Data retrieved successfully',
            ]);
        }catch (Exception $e) {
            return MessageExceptionResponse::response($e);
        }
    }
}
