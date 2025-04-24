<?php

namespace App\Modules\Documents\Payroll;

use App\Models\Location\Cities;
use App\Models\Location\Country;
use Exception;
use Illuminate\Http\Request;

class PayrollGenerationPlace
{
    /**
     * @throws Exception
     */
    public static function get(Request $request): object
    {
        $cityId     = $request->generation_city_id ?? 149;
        $city       = Cities::query()->where('id', $cityId)->first();
        if (!$city) {
            throw new Exception('La ciudad de generación no existe.', 400);
        }
        $department = $city->department;
        $country    = Country::findOrFail($department->country_id ?? 45);
        return (object) [
            'city' => $city,
            'department' => $department,
            'country' => $country
        ];
    }
}
