<?php

namespace App\Models\Invoice;

use App\Core\CoreModel;
use App\Models\Types\TypeDocument;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CorrectionNote extends CoreModel
{
    protected  $table = 'correction_accounting_notes';
    protected $fillable = [
        'document_id',
        'code',
        'description',
    ];
    protected $with = ['document'];
    public function document(): BelongsTo
    {
        return $this->belongsTo(TypeDocument::class, 'document_id');
    }
}
