<?php

namespace App\Models\Invoice;

use App\Core\CoreModel;

class Discount extends CoreModel
{
    public $table   = 'discount_codes';
    protected $fillable = [
        'description', 'code',
    ];
}
