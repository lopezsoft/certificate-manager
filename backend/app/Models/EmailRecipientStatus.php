<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @method static updateOrCreate(array $array, array $array1)
 */
class EmailRecipientStatus extends Model
{
    protected $fillable = [ // O usa $guarded = [] con precaución
        'email_log_id',
        'recipient_email',
        'status',
        'event_type',
        'bounce_type',
        'bounce_subtype',
        'smtp_status',
        'diagnostic_code',
        'event_timestamp'
    ];

    protected $casts = [
        'event_timestamp' => 'datetime'
    ];

    public function emailLog(): BelongsTo
    {
        return $this->belongsTo(EmailLog::class);
    }
}
