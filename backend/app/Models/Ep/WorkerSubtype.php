<?php

namespace App\Models\Ep;

use App\Core\CoreModel;

class WorkerSubtype extends CoreModel
{
    public $table   = 'ep_worker_subtype';
    protected $fillable = [
        'id', 'code', 'worker_subtype_name', 'state',
    ];
}
