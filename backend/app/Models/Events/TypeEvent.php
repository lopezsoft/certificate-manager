<?php

namespace App\Models\Events;

use App\Core\CoreModel;

class TypeEvent extends CoreModel
{

    protected $table = 'type_events';
    protected $fillable = [
        'code',
        'name',
        'responsible',
    ];
}
