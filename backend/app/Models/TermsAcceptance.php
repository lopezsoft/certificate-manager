<?php

namespace App\Models;

use App\Core\CoreModel;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * Evidencia de aceptación de una versión de T&C.
 *
 * Append-only: nunca se actualiza ni se borra desde la aplicación.
 * Polimórfica (acceptable): hoy CertificateRequest; extensible a
 * CertificateOrder sin cambios de esquema.
 */
class TermsAcceptance extends CoreModel
{
    public $table      = 'terms_acceptances';
    public $timestamps = true;

    protected $fillable = [
        'terms_version_id', 'user_id', 'company_id',
        'acceptable_type', 'acceptable_id', 'consent_scope',
        'accepted_at', 'ip_address', 'user_agent',
    ];

    protected $casts = [
        'accepted_at' => 'datetime',
    ];

    public function termsVersion(): BelongsTo
    {
        return $this->belongsTo(TermsVersion::class, 'terms_version_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function acceptable(): MorphTo
    {
        return $this->morphTo();
    }
}
