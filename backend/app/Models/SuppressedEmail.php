<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * @method static updateOrCreate(array $array, array $array1)
 * @method static whereIn(string $string, $recipients)
 */
class SuppressedEmail extends Model
{
    protected $fillable = [
        'email',
        'reason_type',
        'reason_subtype',
        'diagnostic_code',
        'suppressed_at',
        'source',
    ];
}
