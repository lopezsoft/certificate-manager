<?php

namespace App\Models\Invoice;

use App\Core\CoreModel;

class PointOfSale extends CoreModel
{
    public $table   = 'point_of_sale';
    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'cashier_name', 'terminal_number','cashier_type', 'sales_code', 'address', 'sub_total'
    ];
}
