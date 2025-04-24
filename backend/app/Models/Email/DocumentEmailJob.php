<?php

namespace App\Models\Email;

use App\Core\CoreModel;
use App\Models\Company;
use App\Models\ShippingHistory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @method static create(array $array)
 */
class DocumentEmailJob extends CoreModel
{
    protected $table = 'documents_email_job';
    protected $fillable = [
        'document_id',
        'company_id',
        'type_document_id',
        'email_to',
        'created_at',
    ];

    public function document(): BelongsTo
    {
        return $this->belongsTo(ShippingHistory::class, 'document_id', 'id');
    }

    public function company() : BelongsTo
    {
        return $this->belongsTo(Company::class, 'company_id', 'id');
    }
}
