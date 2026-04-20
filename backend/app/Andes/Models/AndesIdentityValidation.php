<?php

namespace App\Andes\Models;

use App\Andes\Enums\AndesTokenStatusEnum;
use App\Andes\Enums\AndesValidationTypeEnum;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AndesIdentityValidation extends Model
{
    protected $table = 'andes_identity_validations';

    protected $fillable = [
        'andes_certificate_request_id',
        'validation_type',
        'token',
        'estado',
        'questions_data',
        'raw_response',
        'attempts',
        'validated_at',
        'expires_at',
    ];

    protected $casts = [
        'estado'         => 'integer',
        'attempts'       => 'integer',
        'questions_data' => 'array',
        'raw_response'   => 'array',
        'validated_at'   => 'datetime',
        'expires_at'     => 'datetime',
    ];

    public function andesCertificateRequest(): BelongsTo
    {
        return $this->belongsTo(AndesCertificateRequest::class, 'andes_certificate_request_id');
    }

    public function getTokenStatusEnum(): AndesTokenStatusEnum
    {
        return AndesTokenStatusEnum::from($this->estado);
    }

    public function getValidationTypeEnum(): ?AndesValidationTypeEnum
    {
        return AndesValidationTypeEnum::tryFrom($this->validation_type);
    }

    public function isValidated(): bool
    {
        return $this->estado === AndesTokenStatusEnum::VALIDADO->value;
    }

    public function isExpired(): bool
    {
        return $this->expires_at !== null && $this->expires_at->isPast();
    }
}

