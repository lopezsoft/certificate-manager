<?php

namespace App\Models\Types;

use App\Core\CoreModel;

class TypeLiability extends CoreModel
{
    public $table   = 'tax_level';

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'description', 'code',
    ];
}
