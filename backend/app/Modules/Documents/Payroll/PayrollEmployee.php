<?php

namespace App\Modules\Documents\Payroll;

use App\Models\Ep\ContractType;
use App\Models\Ep\WorkerSubtype;
use App\Models\Ep\WorkerType;
use App\Models\Invoice\IdentityDocument;
use App\Models\Location\Cities;
use App\Models\Location\Country;
use Illuminate\Http\Request;

class PayrollEmployee
{
    public static function get(Request $request) {
        $employee = $request->employee ?? null;

        if($employee){
            if(is_array($employee)){
                $employee    = (object) array_merge($employee, []);
            }else if(is_string($employee)){
                $employee    = json_decode($employee);
            }

            $employee->worker_type                  = WorkerType::findOrFail($employee->worker_type_id ?? 1);
            $employee->worker_subtype               = WorkerSubtype::findOrFail($employee->worker_subtype_id ?? 1);
            $employee->high_risk_pension            = $employee->high_risk_pension ?? 'false';
            $employee->identity_document            = IdentityDocument::findOrFail($employee->identity_document_id ?? 1);
            $employee->document_number              = $employee->document_number ?? "";
            $employee->first_surname                = $employee->first_surname ?? "";
            $employee->second_surname               = $employee->second_surname ?? null;
            $employee->first_name                   = $employee->first_name ?? "";
            $employee->other_names                  = $employee->other_names ?? null;
            $employee->working_country              = Country::findOrFail($employee->working_country_id ?? 45);
            $employee->work_city                    = Cities::findOrFail($employee->work_city_id ?? 149);
            $employee->work_address                 = $employee->work_address ?? "";
            $employee->integral_salary              = $employee->integral_salary ?? "false";
            $employee->contract_type                = ContractType::findOrFail($employee->contract_type_id ?? 1);
            $employee->salary                       = $employee->salary ?? 0;
            $employee->worker_code                  = $employee->worker_code ?? null;
        }


        return $employee;
    }
}
