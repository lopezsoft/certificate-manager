<?php

namespace App\Models\DocumentSupport;

use App\Core\CoreModel;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class DsInvoicePeriod extends Coremodel
{
    use HasFactory;
    public $table   = "ds_invoice_period";
    protected $fillable = [
      'code', 'description'
    ];
}
