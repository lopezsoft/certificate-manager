<?php

namespace App\Models\Settings;

use App\Core\CoreModel;
use App\Models\Types\TypeCurrency;

class CurrencySys extends CoreModel
{
    protected $table    = "currency_sys";
    protected $appends  = [
        'currency'
    ];
    public function getCurrencyAttribute(): ?object
    {
        return $this->hasOne(TypeCurrency::class, 'id', 'currency_id')->first();
    }
}
