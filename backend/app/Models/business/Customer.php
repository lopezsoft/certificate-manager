<?php

namespace App\Models\business;

use App\Core\CoreModel;
use App\Models\Invoice\IdentityDocument;
use App\Models\Location\Cities;
use App\Models\Location\Country;
use App\Models\Taxes\Tax;
use App\Models\Types\TypeLiability;
use App\Models\Types\TypeOrganization;
use App\Models\Types\TypeRegime;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @method static create(array $customerAll)
 */
class Customer extends CoreModel
{
    /**
     * With default model.
     *
     * @var array
     */

    public $table    = 'customers';


    protected $with = [
        'taxes', 'country', 'identityDocument', 'typeOrganization', 'city', 'taxLevel', 'taxRegime',
    ];

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'country_id', 'city_id',
        'document_id', 'type_organization_id',
        'taxRegime_id', 'taxLevel_id', 'company_name',
        'dni', 'dv', 'address', 'location',
        'postal_code', 'mobile', 'phone', 'email', 'web','merchant_registration',
        'identity_document_id', 'tax_regime_id', 'tax_level_id',
        'city_name'
    ];

    /**
     * The attributes that should be hidden for arrays.
     *
     * @var array
     */
    protected $hidden = [
        'taxes', 'country', 'city', 'taxLevel', 'taxRegime',
    ];

    /**
     * Get the tax that owns the company.
     */
    public function taxes(): BelongsTo
    {
        return $this->belongsTo(Tax::class);
    }

    /**
     * Get the country that owns the company.
     */
    public function country()
    {
        return $this->belongsTo(Country::class);
    }

    /**
     * Get the type document identification that owns the company.
     */
    public function identityDocument(): BelongsTo
    {
        return $this->belongsTo(IdentityDocument::class);
    }


    /**
     * Get the type organization identification that owns the company.
     */
    public function typeOrganization(): BelongsTo
    {
        return $this->belongsTo(TypeOrganization::class);
    }

    /**
     * Get the city identification that owns the company.
     */
    public function city(): BelongsTo
    {
        return $this->belongsTo(Cities::class);
    }

    /**
     * Get the type liability identification that owns the company.
     */
    public function taxLevel(): BelongsTo
    {
        return $this->belongsTo(TypeLiability::class);
    }

    /**
     * Get the type regime identification that owns the company.
     */
    public function taxRegime(): BelongsTo
    {
        return $this->belongsTo(TypeRegime::class);
    }

}
