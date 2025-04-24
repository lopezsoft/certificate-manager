<?php

namespace App\Models\Events;

use App\Core\CoreModel;
use App\Models\business\Customer;
use App\Models\Invoice\PaymentMethod;
use App\Models\Types\TypeDocument;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * @method static create(array $data)
 */
class DocumentReception extends CoreModel
{
    use SoftDeletes;
    public $timestamps = true;
    protected $hidden = ['created_at', 'updated_at', 'deleted_at'];
    protected $with = ['documentType'];
    protected $fillable = [
        'company_id',
        'people_id',
        'document_type_id',
        'payment_method_id',
        'cufe_cude',
        'folio',
        'issue_date',
        'total',
        'document_origin',
        'metadata',
    ];

    protected $casts = [
        'issue_date' => 'datetime:d-m-Y',
        'receipt_date' => 'datetime:d-m-Y h:i:s a',
        'metadata' => 'array',
    ];
    public function people(): BelongsTo
    {
        return $this->belongsTo(Customer::class, 'people_id', 'id')
            ->select('id', 'dni','company_name', 'email', 'address', 'phone')
            ->without(['city', 'type_organization', 'tax_level', 'tax_regime']);
    }
    public function documentType(): BelongsTo
    {
        return $this->belongsTo(TypeDocument::class, 'document_type_id', 'id');
    }
    public function paymentMethod(): BelongsTo
    {
        return $this->belongsTo(PaymentMethod::class, 'payment_method_id', 'id');
    }
    public function events(): HasMany
    {
        return $this->hasMany(EventMaster::class, 'document_reception_id', 'id')
            ->select('id', 'resolution_id', 'document_reception_id', 'type_event_id', 'event_number',
                'date_event', 'description', 'zip_path', 'event_data', 'document_status', 'send_mail')
            ->orderBy('type_event_id')
            ->with(['typeEvent']);
    }
}
