<?php

namespace App\Models;

use App\Models\business\Customer;
use App\Models\Types\TypeDocument;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @method static create(array $array)
 */
class EmailLog extends Model
{
    protected $fillable = [
        'message_id',
        'company_id',
        'customer_id',
        'type_document_id',
        'document_id',
        'email',
        'status',
        'delivered_at',
        'bounced_at',
        'complained_at',
        'opens',
        'last_opened_at',
        'clicks',
        'last_clicked_at',
        'bounce_type',
        'bounce_subtype',
    ];

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class, 'company_id');
    }
    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class, 'customer_id');
    }
    public function typeDocument(): BelongsTo
    {
        return $this->belongsTo(TypeDocument::class, 'type_document_id');
    }
}
