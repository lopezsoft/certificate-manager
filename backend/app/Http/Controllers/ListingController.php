<?php

namespace App\Http\Controllers;

use App\Models\Invoice\IdentityDocument;
use App\Models\Language;
use App\Models\Location\Country;
use App\Models\Types\TypeCurrency;
use App\Models\Types\TypeDocument;
use App\Models\Types\TypeEnvironment;
use App\Models\Types\TypeLiability;
use App\Models\Types\TypeOperation;
use App\Models\Types\TypeOrganization;
use App\Models\Types\TypeRegime;

class ListingController extends Controller
{
    /**
     * index.
     *
     * @return array
     */
    public function index()
    {
        return [
            'Country' => Country::all()->pluck('name', 'id'),
            'Language' => Language::all()->pluck('name', 'id'),
            'TypeRegime' => TypeRegime::all()->pluck('name', 'id'),
            'TypeDocument' => TypeDocument::all()->pluck('name', 'id'),
            'TypeCurrency' => TypeCurrency::all()->pluck('name', 'id'),
            'TypeLiability' => TypeLiability::all()->pluck('name', 'id'),
            'TypeOperation' => TypeOperation::all()->pluck('name', 'id'),
            'TypeEnvironment' => TypeEnvironment::all()->pluck('name', 'id'),
            'TypeOrganization' => TypeOrganization::all()->pluck('name', 'id'),
            'IdentityDocument' => IdentityDocument::all()->pluck('name', 'id'),
        ];
    }
}
