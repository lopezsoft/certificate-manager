<?php

namespace App\Models\Invoice;

use App\Core\CoreModel;

/**
 * @method static findOrFail(int $param)
 */
class DiscrepancyResponse extends CoreModel
{
    //

    public $table   = 'correction_accounting_notes';

    protected $fillable = [
        'document_id', 'code', 'description', 'reference_id'
    ];


    protected $appends = [
        'document_id', 'code', 'description', 'reference_id'
    ];


    function getDescriptionAttribute()
    {
        return $this->attributes['description'] ?? '';
    }

    function getCodeAttribute()
    {
        return $this->attributes['code'] ?? 2;
    }

    function getDocumentIdAttribute()
    {
        return $this->attributes['document_id'] ?? 5;
    }

    function getReferenceIdAttribute()
    {
        return $this->attributes['reference_id'];
    }
}
