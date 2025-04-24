<?php

namespace App\Services\Invoice;

use App\Models\Invoice\SoftwareManufacturer;

class SoftwareManufacturerService
{
    public static function get($request): ?object
    {
        if (isset($request->software_manufacturer)) {
            $softwareManufacturer = $request->software_manufacturer;
            if (is_array($request->software_manufacturer)) {
                $softwareManufacturer   = array_merge($request->software_manufacturer, []);
            } else if (is_string($request->software_manufacturer)) {
                $softwareManufacturer   = json_decode($request->software_manufacturer, TRUE);
            }
            $softwareManufacturer = new SoftwareManufacturer($softwareManufacturer);
        } else {
            $softwareManufacturer = new SoftwareManufacturer([
                "owner_name" => "LEWIS LOPEZ GOMEZ",
                "company_name" => "LOPEZSOFT S.A.S",
                "software_name" =>  "MATIAS API"
            ]);
        }
        return $softwareManufacturer;
    }
}
