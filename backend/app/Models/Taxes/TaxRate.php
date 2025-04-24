<?php

namespace App\Models\Taxes;

use App\Core\CoreModel;

class TaxRate extends CoreModel
{
    public $table = 'tax_rates';
    protected $fillable = [
        'taxe_id', 'rate_name', 'rate_abbre', 'rate_value', 'active',
    ];

    protected  $with = [
        'tax'
    ];

    public function tax(): \Illuminate\Database\Eloquent\Relations\HasOne
    {
        return $this->hasOne(Tax::class, 'id', 'taxe_id');
    }

}
