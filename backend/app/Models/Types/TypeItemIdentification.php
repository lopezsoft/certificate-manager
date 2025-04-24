<?php

namespace App\Models\Types;

use App\Core\CoreModel;

class TypeItemIdentification extends CoreModel
{
    public $table   = 'type_item_identifications';
    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'name', 'code', 'code_agency',
    ];
}
