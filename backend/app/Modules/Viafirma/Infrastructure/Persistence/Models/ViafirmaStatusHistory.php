<?php

declare(strict_types=1);

namespace App\Modules\Viafirma\Infrastructure\Persistence\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Bitácora técnica de la FSM remota Viafirma (granularidad de polling +
 * payloads crudos).
 *
 * @property int $id
 * @property int $viafirma_certificate_request_id
 * @property string|null $previous_state
 * @property string $new_state
 * @property string|null $remote_status
 * @property array|null $raw_response
 * @property int $attempt_number
 * @property \Illuminate\Support\Carbon $occurred_at
 */
class ViafirmaStatusHistory extends Model
{
    protected $table = 'viafirma_status_history';

    public $timestamps = false;

    protected $fillable = [
        'viafirma_certificate_request_id',
        'previous_state',
        'new_state',
        'remote_status',
        'raw_response',
        'attempt_number',
        'occurred_at',
    ];

    protected $casts = [
        'raw_response'   => 'array',
        'attempt_number' => 'integer',
        'occurred_at'    => 'datetime',
    ];

    public function request(): BelongsTo
    {
        return $this->belongsTo(ViafirmaCertificateRequest::class, 'viafirma_certificate_request_id');
    }
}

