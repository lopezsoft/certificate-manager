<?php

namespace App\Models;

use App\Core\CoreModel;
use App\Models\Location\Cities;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;


class CertificateRequest extends CoreModel
{
    /**
     * With default model.
     *
     * @var array
     */

    public $table    = 'certificate_requests';
    public $timestamps = true;

    /**
     * Se eliminó $with global para evitar carga N+1 innecesaria.
     * Cada query debe usar ->with([...]) explícito según sus necesidades.
     */

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'city_id', 'identity_document_id', 'type_organization_id', 'entity_document_type_id',
        'company_name', 'dni', 'dv', 'address', 'document_number',
        'phone', 'mobile', 'legal_representative', 'legal_rep_first_name', 'legal_rep_last_name', 'legal_rep_email', 'info', 'request_status',
        'company_id', 'postal_code', 'life', 'base_path', 'document_type',
        'pin', 'expiration_date', 'issued_at', 'cert_valid_to',
    ];

    protected $casts = [
        'created_at'    => 'datetime:d-m-Y h:i:s a',
        'updated_at'    => 'datetime:d-m-Y h:i:s a',
    ];

    protected $appends = [
        'created_at_formatted',
        'updated_at_formatted',
        'expiration_date_formatted'
    ];

    /**
     * Get the created_at formatted attribute.
     * Carbon instance is used to format the date.
     *
     * @return string
     */

    public function getCreatedAtFormattedAttribute(): string
    {
        return Carbon::parse($this->created_at,'America/Bogota')->format('d-m-Y h:i:s a');
    }
    /**
     * Get the updated_at formatted attribute.
     *
     * @return string
     */
    public function getUpdatedAtFormattedAttribute(): string
    {
        return Carbon::parse($this->updated_at,'America/Bogota')->format('d-m-Y h:i:s a');
    }
    /**
     * Get the expiration_date formatted attribute.
     *
     * @return string
     */

    public function getExpirationDateFormattedAttribute(): ?string
    {
        return $this->expiration_date
            ? Carbon::parse($this->expiration_date, 'America/Bogota')->format('d-m-Y h:i:s a')
            : null;
    }

    /**
     * Get the type document identification that owns the company.
     */
    public function identity(): BelongsTo
    {
        return $this->belongsTo(IdentityDocument::class, 'identity_document_id');
    }


    /**
     * Get the type organization identification that owns the company.
     */
    public function organization(): BelongsTo
    {
        return $this->belongsTo(TypeOrganization::class, 'type_organization_id');
    }

    /**
     * Get the entity document type (Cámara de Comercio, Personería Jurídica, etc.).
     */
    public function entityDocumentType(): BelongsTo
    {
        return $this->belongsTo(EntityDocumentType::class, 'entity_document_type_id');
    }

    /**
     * Get the city identification that owns the company.
     */
    public function city(): BelongsTo
    {
        return $this->belongsTo(Cities::class, 'city_id');
    }

    /**
     * Get the company that owns the certificate request.
     */
    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class, 'company_id');
    }
    /**
     * Get the files that owns the certificate request.
     */
    public function files(): HasMany
    {
        return $this->hasMany(FileManager::class, 'certificate_request_id');
    }

    /**
     * Get the history that owns the certificate request.
     */
    public function history(): HasMany
    {
        return $this->hasMany(ChangeHistory::class, 'certificate_request_id')->orderBy('created_at', 'desc');
    }

    /**
     * Get the document analysis results for this certificate request.
     */
    public function documentAnalysisResults(): HasMany
    {
        return $this->hasMany(DocumentAnalysisResult::class, 'certificate_request_id');
    }

    /**
     * Get the latest document analysis result.
     */
    public function latestDocumentAnalysis()
    {
        return $this->hasOne(DocumentAnalysisResult::class, 'certificate_request_id')->latest();
    }

    /**
     * Validación centralizada: solo se puede descargar si el certificado está en estado PROCESSED.
     * Esta es la fuente de verdad para determinar si un certificado está listo para descarga.
     */
    public function canDownloadCertificate(): bool
    {
        return $this->request_status === \App\Enums\CertificateRequestStatusEnum::PROCESSED->value;
    }
}
