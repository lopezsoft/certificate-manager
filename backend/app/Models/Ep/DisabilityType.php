<?php

namespace App\Models\Ep;

use App\Core\CoreModel;

class DisabilityType extends CoreModel
{
    public $table   = 'ep_disability_type';
    protected $fillable = [
        'code', 'inability_name', 'state',
    ];
}
