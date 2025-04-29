<?php

namespace App\Models;

use App\Core\CoreModel;
use App\Models\Location\Cities;
use App\Models\Location\Country;
use App\Models\Settings\GeneralSettingCompany;
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
        'country','identity_document', 'type_organization', 'city'
    ];

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'country_id', 'city_id', 'identity_document_id', 'type_organization_id',
        'company_name', 'dni', 'dv', 'address', 'city_name',
        'location', 'postal_code',  'phone', 'email',  'image'
    ];

    /**
     * The attributes that should be hidden for arrays.
     *
     * @var array
     */
    protected $hidden = [
        'country', 'identity_document', 'type_organization', 'city'
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
}
