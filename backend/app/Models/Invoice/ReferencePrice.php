<?php

namespace App\Models\Invoice;

use App\Core\CoreModel;

class ReferencePrice extends CoreModel
{
    public $table   = 'reference_price';
    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'description', 'code',
    ];
}
