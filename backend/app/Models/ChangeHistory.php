<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @method static create(array $array)
 */
class ChangeHistory extends Model
{

    protected $table = 'change_histories';

    protected $fillable = [
        'certificate_request_id',
        'user_id',
        'user_of_change',
        'status',
        'comments',
    ];

    protected $with = [
        'user',
    ];

    protected $casts = [
        'created_at' => 'datetime:d-m-Y h:i:s a',
        'updated_at' => 'datetime:d-m-Y h:i:s a',
    ];

    protected $appends = [
        'created_at_formatted',
        'updated_at_formatted',
    ];

    public function getCreatedAtFormattedAttribute(): string
    {
        return Carbon::parse($this->created_at, 'America/Bogota')->format('d-m-Y h:i:s a');
    }

    public function getUpdatedAtFormattedAttribute(): string
    {
        return Carbon::parse($this->updated_at, 'America/Bogota')->format('d-m-Y h:i:s a');
    }



    public function certificateRequest(): BelongsTo
    {
        return $this->belongsTo(CertificateRequest::class, 'certificate_manager_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

}
