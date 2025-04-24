<?php

namespace App\Modules\Company;

use App\Models\Company;
use App\Models\Settings\Software;
use Exception;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class CompanyQueries
{
    public static function getSoftware(Request $request, Company $company): ?object
    {
        $type_id    = $request->type_id ?? 1;
        return Software::query()
            ->where('type_id', $type_id)
            ->where('company_id', $company->id)
            ->first();
    }
    /**
     * @throws Exception
     */
    public static function getCompanyByRequest(Request $request): Company | null
    {
        $company_id = $request->input('companyId');
        if(isset($company_id)){
            $company    = Company::query()->where('id',$company_id)->first();
            self::validate($company);
        }else{
            $company    = self::getCompany();
        }
        return $company;
    }

    /**
     * @throws Exception
     */
    public static function getCompany(): Company
    {
        $user       = Auth::user();
        $buser      = DB::table('business_users')->where('user_id', $user->id)->first();
        $company    = Company::where('id', $buser->company_id)->first();
        self::validate($company);
        return  $company;
    }

    public static function getCompanyId()
    {
        $user   = Auth::user();
        $buser  = DB::table('business_users')->where('user_id', $user->id)->first();
        return $buser->company_id ?? 0;
    }

    protected static function validate($company): void
    {
        if (!$company) {
            throw new Exception('No se encontró la empresa asociada al usuario.', 404);
        }

        if($company->active === 0) {
            throw new AuthorizationException('La empresa se encuentra inactiva.', 403);
        }
    }
}
