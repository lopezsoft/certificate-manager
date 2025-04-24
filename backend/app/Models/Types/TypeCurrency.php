<?php

namespace App\Models\Types;

use App\Core\CoreModel;

/**
 * @method static findOrFail(int $param)
 */
class TypeCurrency extends CoreModel
{

    public $table   = 'currency';
    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'CurrencyISO', 'Language', 'CurrencyName', 'Money','Symbol', 'active'
    ];
}
