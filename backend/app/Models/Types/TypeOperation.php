<?php

namespace App\Models\Types;

use App\Core\CoreModel;

/**
 * @method static findOrFail(mixed $param)
 */
class TypeOperation extends CoreModel
{
    public $table   = 'operation_types';
    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'name', 'code',
    ];
}
