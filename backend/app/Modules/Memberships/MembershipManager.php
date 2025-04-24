<?php

namespace App\Modules\Memberships;

use App\Classes\CompanyClass;
use App\Common\HttpResponseMessages;
use App\Models\Memberships\CompanyMembership;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class MembershipManager
{
    /**
     * @throws \Exception
     */
    public static function getActive(Request $request): ? object
    {
        $tz         = new \DateTimeZone('America/Bogota');
        $company    = CompanyClass::getCompany($request);
        $query      = DB::select("CALL sp_company_membership(?)", [$company->id]);

        $query      = $query[0];

        if($query) {
            $query->lock_date   = (new \DateTime($query->lock_date))->setTimezone($tz)->format('d-m-Y');
            $query->isActive    = ($query->active == 1 && $query->days >= 0);
        }
        return (object) [
            'query'     => $query,
            'company'   => $company
        ];
    }

    public static function getMembershipData($company): object
    {
        return  CompanyMembership::query()
            ->where('company_id', $company->id)
            ->with('membership')->first();
    }

    /**
     * @throws \Exception
     */
    public static function getConsumedDocuments(Request $request): JsonResponse
    {
        $company    = CompanyClass::getCompany($request);
        $query      = DB::select("CALL sp_select_consumed_documents(?)", [$company->id]);

        return HttpResponseMessages::getResponse([
            'dataRecords'   => [
                'data'  => $query,
            ]
        ]);
    }
}
