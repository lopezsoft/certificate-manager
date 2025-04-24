<?php

namespace App\Models;

use App\Core\CoreModel;

class QuantityUnit extends CoreModel
{

    public $table   = 'quantity_units';
    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'name', 'code',
    ];
}
