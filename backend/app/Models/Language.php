<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Core\CoreModel;

/**
 * @method static findOrFail(mixed $language)
 */
class Language extends CoreModel
{

    public $table   = 'languages';
    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'Language', 'LanguageName', 'ISO_639_1', 'ISO_639_2',
    ];
}
