<?php

namespace App\Models;

use App\Core\CoreModel;

class TypeOrganization extends CoreModel
{
    public $table   = 'type_organization';
    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'name', 'description',
    ];
}
