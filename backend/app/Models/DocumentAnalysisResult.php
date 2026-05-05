<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Resultado de análisis IA de un documento.
 *
 * Almacena texto OCR + respuesta IA + datos extraídos.
 */
class DocumentAnalysisResult extends Model
{
    protected $table = 'document_analysis_results';

    protected $fillable = [
        'certificate_request_id',
        'file_manager_id',
        'provider',
        'analysis_type',
        'ocr_text',
        'ocr_provider',
        'ocr_confidence',
        'ai_response',
        'ai_confidence',
        'completeness_score',
        'extracted_data',
        'validation_result',
        'processing_time',
        'status',
        'error_message',
        'processed_by',
    ];

    protected $casts = [
        'ocr_confidence'    => 'decimal:2',
        'ai_confidence'     => 'decimal:2',
        'completeness_score' => 'decimal:2',
        'processing_time'   => 'decimal:3',
        'ai_response'       => 'array',
        'extracted_data'    => 'array',
        'validation_result' => 'array',
    ];

    public function certificateRequest(): BelongsTo
    {
        return $this->belongsTo(CertificateRequest::class, 'certificate_request_id');
    }

    public function processedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'processed_by');
    }

    public function isCompleted(): bool
    {
        return $this->status === 'COMPLETED';
    }

    public function isFailed(): bool
    {
        return $this->status === 'FAILED';
    }
}
