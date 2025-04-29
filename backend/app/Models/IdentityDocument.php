<?php

namespace App\Models;

use App\Core\CoreModel;

class IdentityDocument extends CoreModel
{
    public $table   = 'identity_documents';
    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'code', 'document_name'
    ];
}
