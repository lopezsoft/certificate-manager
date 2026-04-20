<?php

namespace App\Andes\Models;

use App\Models\CertificateRequest;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AndesCertificateRequest extends Model
{
    protected $table = 'andes_certificate_requests';

    protected $fillable = [
        'certificate_request_id',
        'andes_solicitud_id',
        'tipo_cert',
        'formato',
        'vigencia_cert',
        'andes_estado',
        'andes_message',
        'andes_raw_response',
        'pin_hash',
        'certificate_serial',
        'emitted_at',
        'revoked_at',
    ];

    protected $casts = [
        'andes_raw_response' => 'array',
        'emitted_at'         => 'datetime',
        'revoked_at'         => 'datetime',
    ];

    public function certificateRequest(): BelongsTo
    {
        return $this->belongsTo(CertificateRequest::class, 'certificate_request_id');
    }

    public function identityValidations(): HasMany
    {
        return $this->hasMany(AndesIdentityValidation::class, 'andes_certificate_request_id');
    }

    public function latestValidation(): \Illuminate\Database\Eloquent\Relations\HasOne
    {
        return $this->hasOne(AndesIdentityValidation::class, 'andes_certificate_request_id')->latest();
    }

    public function isEmitted(): bool
    {
        return ! empty($this->certificate_serial) && $this->emitted_at !== null;
    }

    public function isRevoked(): bool
    {
        return $this->revoked_at !== null;
    }
}

