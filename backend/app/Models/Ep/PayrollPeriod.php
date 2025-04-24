<?php

namespace App\Models\Ep;

use App\Core\CoreModel;

class PayrollPeriod extends CoreModel
{
    public $table    = 'ep_payroll_period';
    protected $fillable = [
        'id', 'code', 'period_name', 'state', 
    ];
}
