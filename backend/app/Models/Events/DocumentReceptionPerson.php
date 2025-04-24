<?php

namespace App\Models\Events;

use App\Core\CoreModel;
use App\Models\Company;
use App\Models\Invoice\IdentityDocument;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DocumentReceptionPerson extends CoreModel
{
    public $timestamps = true;
    protected $table = 'document_reception_people';

    protected $fillable = [
        'company_id',
        'identity_document_id',
        'dni',
        'dv',
        'first_name',
        'last_name',
        'email',
        'job_title',
        'department',
        'send_events',
    ];

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function identityDocument(): BelongsTo
    {
        return $this->belongsTo(IdentityDocument::class, 'identity_document_id', 'id');
    }
}
