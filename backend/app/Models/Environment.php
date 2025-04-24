<?php

namespace App\Models;

use App\Core\CoreModel;

class Environment extends CoreModel
{
    //
     /**
     * The table name
    */

    public $table    = 'destination_environment';

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'environment_name', 'code',
    ];
}
