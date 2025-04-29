<?php

namespace App\Modules\Company;
use App\Common\VerificationDigit;
use App\Models\Company;
use Exception;
use Illuminate\Http\Request;

class CreatedCompany
{
    /**
     * @throws Exception
     */
    public static function create(Request $request)
    {
        try {
            $dv         = VerificationDigit::getDigit($request->dni);
            return Company::create([
                'city_id'               => $request->city_id ?? 149,
                'country_id'            => $request->country_id ?? 45,
                'identity_document_id'  => $request->identity_document_id ?? 3,
                'type_organization_id'  => $request->type_organization_id ?? 1,
                'company_name'          => $request->company_name,
                'dni'                   => $request->dni,
                'dv'                    => $dv,
                'address'               => $request->address ?? '',
                'location'              => $request->location ?? '',
                'postal_code'           => $request->postal_code ?? '',
                'phone'                 => $request->phone ?? '',
                'email'                 => $request->email
            ]);
        }catch (Exception $e){
            throw new Exception($e->getMessage());
        }
    }
}
