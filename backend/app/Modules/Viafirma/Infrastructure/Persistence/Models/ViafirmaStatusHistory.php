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
 * @property \Illuminate\Support\Carbon $created_at Momento en que inicia el episodio de estado (fijo, no se actualiza tras el INSERT)
 * @property int $poll_count_in_state Cantidad de polls que confirmaron este mismo estado sin cambios
 * @property \Illuminate\Support\Carbon $occurred_at Última vez que se confirmó este estado (se actualiza en cada poll)
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
        'created_at',
        'poll_count_in_state',
        'occurred_at',
    ];

    protected $casts = [
        'raw_response'         => 'array',
        'attempt_number'       => 'integer',
        'created_at'           => 'datetime',
        'poll_count_in_state'  => 'integer',
        'occurred_at'          => 'datetime',
    ];

    public function request(): BelongsTo
    {
        return $this->belongsTo(ViafirmaCertificateRequest::class, 'viafirma_certificate_request_id');
    }
}

