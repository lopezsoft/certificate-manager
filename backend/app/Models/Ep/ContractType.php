<?php

namespace App\Models\Ep;

use App\Core\CoreModel;

class ContractType extends CoreModel
{
    public $table   = 'ep_contract_type';

    protected $fillable = [
        'code', 'contrac_type_name', 'state',
    ];
}
