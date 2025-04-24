<?php

namespace App\Models\Settings;

use App\Core\CoreModel;

/**
 * @method static create(array $record)
 */
class Certificate extends CoreModel
{

    public $table   = 'certificates';

    protected $fillable = [
        'company_id', 'data', 'description','password','extension','name', 'expiration_date'
    ];

    protected $casts = [
        'expiration_date' => 'datetime:d-m-Y h:i:s a',
    ];

    protected $hidden = [
        'company_id', 'path', 'password'
    ];

    protected $appends = [
        'path',
    ];

    public function getPathAttribute(): string
    {
        return storage_path("app/certificates/{$this->name}");
    }
}
