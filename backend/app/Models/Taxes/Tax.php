<?php

namespace App\Models\Taxes;

use App\Core\CoreModel;

/**
 * @method static findOrFail(mixed $tax_id)
 */
class Tax extends CoreModel
{
    public $table   = 'taxes';
    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'name', 'description', 'code',
    ];
}
