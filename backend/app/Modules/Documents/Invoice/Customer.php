<?php

namespace App\Modules\Documents\Invoice;

use App\Common\VerificationDigit;
use App\Models\Company;
use App\Models\User;
use Illuminate\Http\Request;

class Customer
{

    public static function getCustomer(Request $request): User
    {
        if ($request->customer) {
            $customerAll = $request->customer;

            if (!is_array($customerAll)) {
                $customerAll = json_decode($request->customer, true);
            }
            return self::getCustomerData($customerAll);
        } else {
            return self::getFinalConsumer();
        }
    }
    public static function getFinalConsumer(): User
    {
        $customerAll = [
            'identity_document_id' => 1,
            'tax_level_id' => 5,
            'type_organization_id' => 2,
            'company_name' => 'Consumidor Final',
            'tax_regime_id' => 2,
            'country_id' => 45,
            'dni' => '222222222222',
        ];

        return self::getCustomerData($customerAll);
    }

    public static function isFinalConsumer($dni = null): bool
    {
        return isFinalConsumer($dni);
    }

    public static function getCustomerData($data = []): User
    {
        $customer       = new User($data);
        // Customer company
        $customer->company                  = new Company($data);
        $customer->company->dv              = VerificationDigit::getDigit(intval($customer->company->dni));
        $customer->points                   = $customer->company->points ?? 0;
        $customer->dni                      = $customer->company->dni;
        $customer->email                    = $customer->company->email;
        return $customer;
    }

    public static function getCustomerAllData($data = []): Object
    {
        $customer       = new User($data);
        // Customer company
        $customer->company                  = new Company($data);
        $customer->company->dv              = VerificationDigit::getDigit(intval($customer->company->dni));
        $customer->points                   = $customer->company->points ?? 0;
        $customer->dni                      = $customer->company->dni;
        $customer->city                     = $customer->company->city;
        $customer->country                  = $customer->company->country;
        $customer->identityDocument         = $customer->company->identity_document;
        $customer->typeOrganization         = $customer->company->type_organization;
        $customer->taxLevel                 = $customer->company->tax_level;
        $customer->taxRegime                = $customer->company->tax_regime;
        $customer->postal_code              = $customer->company->postal_code;
        $customer->email                    = $customer->company->email;
        $customer->mobile                   = $customer->company->mobile;
        $customer->address                  = $customer->company->address;
        $customer->company_name             = $customer->company->company_name;
        $customer->dv                       = $customer->company->dv;
        $customer->phone                    = $customer->company->phone;
        $customer->trade_name               = $customer->company->trade_name;
        $customer->city_name                = $customer->company->city_name;

        return $customer;
    }


}
