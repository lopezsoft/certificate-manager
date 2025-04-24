<?php

namespace App\Models\user;

use App\Core\CoreModel;

/**
 * @method static get()
 */
class UserType extends CoreModel
{
    public $table   = 'user_types';

     /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'user_type_name', 'type', 'active',
    ];

}
