<?php

namespace App\Models\Location;

use App\Core\CoreModel;

class Department extends CoreModel
{

    public $table   = 'departments';
    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'country_id', 'name_department', 'code','abbreviation',
    ];

    /**
     * The attributes that should be hidden for arrays.
     *
     * @var array
     */
    protected $hidden = [
        'country_id',
    ];
}
