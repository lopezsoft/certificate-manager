<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DocumentAnalysisResult extends Model
{
    use HasFactory;

    protected $fillable = [
        'certificate_request_id',
        'analysis_results',
        'validation_summary',
        'validation_errors',
        'extracted_data',
        'overall_valid',
        'rut_found',
        'person_type',
        'chamber_commerce_required',
        'chamber_commerce_found',
        'chamber_commerce_valid_date',
        'cedula_found',
        'cedula_complete',
        'legal_representative_match',
        'documents_processed',
        'processing_time'
    ];

    protected $casts = [
        'analysis_results' => 'array',
        'validation_summary' => 'array',
        'validation_errors' => 'array',
        'extracted_data' => 'array',
        'overall_valid' => 'boolean',
        'rut_found' => 'boolean',
        'chamber_commerce_required' => 'boolean',
        'chamber_commerce_found' => 'boolean',
        'chamber_commerce_valid_date' => 'boolean',
        'cedula_found' => 'boolean',
        'cedula_complete' => 'boolean',
        'legal_representative_match' => 'boolean',
        'processing_time' => 'decimal:4'
    ];

    /**
     * Get the certificate request that owns this analysis result
     */
    public function certificateRequest(): BelongsTo
    {
        return $this->belongsTo(CertificateRequest::class);
    }

    /**
     * Scope for valid analyses
     */
    public function scopeValid($query)
    {
        return $query->where('overall_valid', true);
    }

    /**
     * Scope for invalid analyses
     */
    public function scopeInvalid($query)
    {
        return $query->where('overall_valid', false);
    }

    /**
     * Scope for juridical persons
     */
    public function scopeJuridical($query)
    {
        return $query->where('person_type', 'juridica');
    }

    /**
     * Scope for natural persons
     */
    public function scopeNatural($query)
    {
        return $query->where('person_type', 'natural');
    }

    /**
     * Get validation status as text
     */
    public function getValidationStatusAttribute(): string
    {
        if ($this->overall_valid) {
            return 'Válido';
        }

        $errors = $this->validation_errors ?? [];
        return 'Inválido (' . count($errors) . ' errores)';
    }

    /**
     * Get person type in Spanish
     */
    public function getPersonTypeSpanishAttribute(): string
    {
        return match($this->person_type) {
            'natural' => 'Persona Natural',
            'juridica' => 'Persona Jurídica',
            default => 'No determinado'
        };
    }
}
