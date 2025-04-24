<?php

namespace App\Models\Events;


use App\Core\CoreModel;
use App\Enums\DocumentStatusEnum;
use App\Models\Company;
use App\Models\Settings\Resolution;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @method static create(array $array)
 */
class EventMaster extends CoreModel
{
    public $timestamps = true;
    protected $table = 'event_masters';
    protected $hidden = ['created_at', 'updated_at'];
    protected $fillable = [
        'company_id',
        'resolution_id',
        'document_reception_id',
        'type_event_id',
        'event_number',
        'date_event',
        'description',
        'xml_path',
        'zip_path',
        'event_data',
        'document_status',
        'send_mail',
    ];

    protected $appends = ['statusDescription'];
    protected $casts = [
        'date_event' => 'datetime:d-m-Y h:i:s a',
        'event_data' => 'array',
    ];

    public function getStatusDescriptionAttribute() : string {
        return DocumentStatusEnum::getDescription($this->document_status);
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function resolution(): BelongsTo
    {
        return $this->belongsTo(Resolution::class);
    }

    public function documentReception(): BelongsTo
    {
        return $this->belongsTo(DocumentReception::class);
    }

    public function typeEvent(): BelongsTo
    {
        return $this->belongsTo(TypeEvent::class);
    }
}
