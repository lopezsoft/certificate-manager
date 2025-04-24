<?php

namespace App\Models\Types;

use App\Core\CoreModel;

class TypeRegime extends CoreModel
{
    public $table   = 'tax_regime';
    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'description', 'code',
    ];
}
