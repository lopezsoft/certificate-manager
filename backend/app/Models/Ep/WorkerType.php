<?php

namespace App\Models\Ep;

use App\Core\CoreModel;

class WorkerType extends CoreModel
{
    public $table   = 'ep_worker_type';
    protected $fillable = [
        'id', 'code', 'worker_type_name', 'state',
    ];
}
