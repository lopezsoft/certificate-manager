<?php

namespace App\Models\Types;

use App\Core\CoreModel;

class TypeEnvironment extends CoreModel
{
    public $table   = 'destination_environme';
    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'environment_name', 'code',
    ];
}
