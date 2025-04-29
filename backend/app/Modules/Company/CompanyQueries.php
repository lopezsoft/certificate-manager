<?php

namespace App\Modules\Company;

use App\Models\Company;
use Exception;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class CompanyQueries
{
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
    /**
     * @throws Exception
     */
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
