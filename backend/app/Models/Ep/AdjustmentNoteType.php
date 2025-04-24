<?php

namespace App\Models\Ep;

use App\Core\CoreModel;

/**
 * @method static findOrFail(int $int)
 */
class AdjustmentNoteType extends CoreModel
{
    public $table   = 'ep_adjustment_note_type';

    protected $fillable = [
        'code', 'setting_name', 'state',
    ];
}
