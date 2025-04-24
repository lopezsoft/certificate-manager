<?php

namespace App\Modules\Company;
use App\Common\VerificationDigit;
use App\Models\Company;
use App\Models\Memberships\CompanyMembership;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class CreatedCompany
{
    /**
     * @throws Exception
     */
    public static function create(Request $request)
    {
        try {
            $dv         = VerificationDigit::getDigit($request->dni);
            $company    = Company::create([
                'country_id'            => $request->country_id ?? 45,
                'city_id'               => $request->city_id ?? 149,
                'identity_document_id'  => $request->identity_document_id ?? 3,
                'type_organization_id'  => $request->type_organization_id ?? 1,
                'tax_regime_id'         => $request->tax_regime_id ?? 1,
                'tax_level_id'          => $request->tax_level_id ?? 5,
                'company_name'          => $request->company_name,
                'dni'                   => $request->dni,
                'dv'                    => $dv,
                'address'               => $request->address ?? '',
                'location'              => $request->location ?? '',
                'postal_code'           => $request->postal_code ?? '',
                'mobile'                => $request->mobile ?? '',
                'phone'                 => $request->phone ?? '',
                'email'                 => $request->email,
                'web'                   => $request->web ?? ''
            ]);

            CompanyMembership::create([
                'company_id'            => $company->id,
                'membership_plan_id'    => 1,
                'activation_date'       => null,
                'lock_date'             => null,
                'active'                => 1,
            ]);
            if (isset($request->type_id) && isset($request->company_id)) {
                DB::table('auxiliary_companies')->insert([
                    'company_id'    => $request->company_id,
                    'customer_id'   => $company->id
                ]);

                DB::table('membership_activation_history')->insert([
                    'company_id'    => $request->company_id,
                    'customer_id'   => $company->id
                ]);
            }
            return $company;
        }catch (Exception $e){
            throw new Exception($e->getMessage());
        }
    }
}
