<?php

namespace App\Models\Settings;

use App\Core\CoreModel;
use App\Models\Settings\GeneralSetting;
use App\Models\Company;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class GeneralSettingCompany extends CoreModel
{
    public $table = 'general_setting_companies';

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class, 'company_id', 'id');
    }

    public function setting(): BelongsTo
    {
        return $this->belongsTo(GeneralSetting::class, 'setting_id', 'id');
    }
}
