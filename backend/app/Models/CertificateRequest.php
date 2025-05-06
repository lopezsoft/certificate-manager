<?php

namespace App\Models;

use App\Core\CoreModel;
use App\Models\Location\Cities;
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

    protected $with = [
        'identity', 'organization', 'city', 'files', 'history'
    ];

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'city_id', 'identity_document_id', 'type_organization_id',
        'company_name', 'dni', 'dv', 'address', 'document_number',
        'phone', 'mobile',  'legal_representative', 'info',  'request_status',
        'company_id', 'postal_code', 'life', 'base_path', 'document_type',
        'pin', 'expiration_date',
    ];

    protected $casts = [
        'created_at'    => 'datetime:d-m-Y h:i:s a',
        'updated_at'    => 'datetime:d-m-Y h:i:s a',
    ];
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
}
