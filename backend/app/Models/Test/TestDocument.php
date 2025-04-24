<?php

namespace App\Models\Test;

use App\Models\Types\TypeDocument;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @method static create(array $array)
 */
class TestDocument extends Model
{
    protected $table = 'test_documents';
    protected $with = [
        'document',
    ];
    protected $fillable = [
        'process_id',
        'type_document_id',
        'document_number',
        'zipkey',
        'XmlDocumentKey',
    ];
    protected $hidden = [
        'created_at',
        'updated_at',
    ];

    public function document() : BelongsTo
    {
        return $this->belongsTo(TypeDocument::class, 'type_document_id');
    }
    public function process() : BelongsTo
    {
        return $this->belongsTo(TestProcess::class, 'process_id');
    }
}
