<?php

namespace App\Models;

use App\Core\CoreModel;
use App\Models\Invoice\IdentityDocument;
use App\Models\Location\Cities;
use App\Models\Location\Country;
use App\Models\Settings\Certificate;
use App\Models\Settings\GeneralSettingCompany;
use App\Models\Settings\Resolution;
use App\Models\Settings\Software;
use App\Models\Taxes\Tax;
use App\Models\Types\TypeLiability;
use App\Models\Types\TypeOrganization;
use App\Models\Types\TypeRegime;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @method static create(int[] $array)
 * @method static where(string $string, mixed $uid)
 * @method static select(string $string, string $string1)
 * @method static find($company_id)
 * @property mixed $certificate
 * @property mixed $software
 * @property mixed $company_name
 */
class Company extends CoreModel
{
    /**
     * With default model.
     *
     * @var array
     */

    public $table    = 'companies';


    protected $with = [
        'software', 'certificate', 'resolutions', 'taxes', 'country',
        'identity_document', 'type_organization', 'city', 'tax_level', 'tax_regime', 'send',
    ];

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'country_id', 'city_id', 'identity_document_id', 'type_organization_id', 'tax_regime_id', 'tax_level_id',
        'company_name', 'trade_name', 'dni', 'dv', 'address', 'city_name',
        'location', 'postal_code', 'mobile', 'phone', 'email', 'web', 'merchant_registration', 'image'
    ];

    /**
     * The attributes that should be hidden for arrays.
     *
     * @var array
     */
    protected $hidden = [
        'software', 'certificate', 'resolutions', 'taxes', 'country', 'identity_document', 'type_organization', 'city', 'tax_level', 'tax_regime', 'send',
    ];

    protected $appends = [
        'full_path_image',
    ];

    public function getFullPathImageAttribute(): string
    {
        return $this->image ? url($this->image) : "";
    }

    public function settings(): HasMany
    {
        return $this->hasMany(GeneralSettingCompany::class)
            ->select('general_setting_companies.*')
            ->join('general_settings as gs', 'gs.id', '=', 'general_setting_companies.setting_id')
            ->where('gs.active', 1)
            ->orderBy('gs.tag')
            ->with(['setting']);
    }

    /**
     * Get the software record associated with the company.
     */
    public function software(): \Illuminate\Database\Eloquent\Relations\HasOne
    {
        return $this->hasOne(Software::class);
    }

    /**
     * Get the certificate record associated with the company.
     */
    public function certificate()
    {
        return $this->hasOne(Certificate::class);
    }

    /**
     * Get the resolutions record associated with the company.
     */
    public function resolutions()
    {
        return $this->hasMany(Resolution::class);
    }


    /**
     * Get the tax that owns the company.
     */
    public function taxes(): BelongsTo
    {
        return $this->belongsTo(Tax::class)
            ->withDefault([
                'id'            => 1,
                'name_taxe'     => 'IVA',
                'description'   => 'Impuesto de Valor Agregado',
                'code'          => '01',
            ]);
    }

    /**
     * Get the country that owns the company.
     */
    public function country(): BelongsTo
    {
        return $this->belongsTo(Country::class)
            ->withDefault([
                'id'                => 45,
                'country_name'      => 'Colombia',
                'abbreviation_A2'   => 'CO',
                'HASC'              => 'CO',
                'active'            => 1,
            ]);
    }

    /**
     * Get the type document identification that owns the company.
     */
    public function identity_document(): BelongsTo
    {
        return $this->belongsTo(IdentityDocument::class)
            ->withDefault([
                'id' => 3,
                'name' => 'NIT',
                'code' => '31',
            ]);
    }


    /**
     * Get the type organization identification that owns the company.
     */
    public function type_organization(): BelongsTo
    {
        return $this->belongsTo(TypeOrganization::class)
            ->withDefault([
                'id' => 2,
                'description' => 'Persona Natural',
                'code' => '2',
            ]);
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
    public function tax_level(): BelongsTo
    {
        return $this->belongsTo(TypeLiability::class)
            ->withDefault([
                'id' => 5,
                'description' => 'No aplica – Otros',
                'code' => 'R-99-PN',
            ]);
    }

    /**
     * Get the type regime identification that owns the company.
     */
    public function tax_regime(): BelongsTo
    {
        return $this->belongsTo(TypeRegime::class)
            ->withDefault([
                'id' => 2,
                'description' => 'No responsable de IVA',
                'code' => '49',
            ]);
    }

    /**
     * Get the send that owns the company.
     */
    public function send()
    {
        return $this->hasMany(Send::class)
            ->where('year', now()->format('y'));
    }
}
