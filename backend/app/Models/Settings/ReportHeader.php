<?php

namespace App\Models\Settings;

use App\Core\CoreModel;

class ReportHeader extends CoreModel
{
    protected $table = 'reports_header';
    protected $fillable = [
        'company_id',
        'line1',
        'line2',
        'foot',
        'image',
        'mime',
    ];
}
