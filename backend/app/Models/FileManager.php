<?php

namespace App\Models;

use App\Core\CoreModel;
use Illuminate\Support\Str;

/**
 * @method static create(array $array)
 * @method static find($id)
 * @property mixed $location
 * @property mixed $file_path
 * @property mixed certificate_request_id
 */
class FileManager extends CoreModel
{
    public $timestamps = true;

    protected $casts = [
        'created_at'    => 'datetime:d-m-Y h:i:s',
        'updated_at'    => 'datetime:d-m-Y h:i:s',
        'last_modified' => 'datetime:d-m-Y h:i:s',
    ];
    protected $fillable = [
        'certificate_request_id', 'file_name', 'extension_file', 'mime_type', 'file_size',
        'last_modified', 'status', 'file_path', 'uuid', 'location', 'upload_status', 'document_type'
    ];
    protected $hidden = [
        'certificate_request_id'
    ];
    protected static function boot(): void
    {
        parent::boot();

        static::creating(function ($fileManager) {
            $fileManager->uuid =  Str::uuid();
        });
    }

    public function certificate(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(CertificateRequest::class, 'certificate_request_id');
    }

}
