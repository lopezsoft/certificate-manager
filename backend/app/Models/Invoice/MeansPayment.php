<?php

namespace App\Models\Invoice;

use App\Core\CoreModel;

/**
 * @method static findOrFail($means_payment_id)
 */
class MeansPayment extends CoreModel
{

    public $table   = 'means_payment';
    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'means_payment', 'code',
    ];
}
