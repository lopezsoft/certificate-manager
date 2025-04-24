<?php

namespace App\Models\Invoice;

use App\Core\CoreModel;

class SoftwareManufacturer extends CoreModel
{
    protected $table   = 'software_manufacturer';
    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'owner_name', 'company_name', 'software_name'
    ];
}
