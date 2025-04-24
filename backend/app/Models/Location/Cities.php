<?php

namespace App\Models\Location;

use App\Core\CoreModel;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @method static findOrFail(mixed $param)
 */
class Cities extends CoreModel
{

    public $table   = 'cities';
    /**
     * With default model.
     *
     * @var array
     */
    protected $with = [
        'department',
    ];

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'department_id', 'name_city', 'city_code',
    ];

    /**
     * The attributes that should be hidden for arrays.
     *
     * @var array
     */
    protected $hidden = [
        'department_id',
    ];
    public function postalCode(): HasMany
    {
        return $this->hasMany(PostalCode::class, 'city_id');
    }
    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class);
    }
}
