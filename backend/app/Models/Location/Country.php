<?php

namespace App\Models\Location;

use App\Core\CoreModel;

/**
 * @method static findOrFail(int $param)
 */
class Country extends CoreModel
{
    public $table   = 'countries';
    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'abbreviation_A2', 'country_name','phone_code',
    ];
}
