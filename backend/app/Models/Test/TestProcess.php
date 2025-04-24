<?php

namespace App\Models\Test;

use App\Enums\DocumentTestStatusEnum;
use App\Models\Settings\Software;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

/**
 * @method static find($processId)
 */
class TestProcess extends Model
{
    protected $table = 'test_process';
    protected $fillable = [
        'uuid',
        'status',
        'user_id',
        'software_id',
        'error_message',
        'StatusDescription'
    ];
    protected $casts = [
        'error_message' => 'array',
    ];
    protected $hidden = [
        'created_at',
        'updated_at',
    ];
    protected $appends = [
        'status_description',
    ];
    protected $with = [
        'documents'
    ];
     protected static function boot(): void
     {
        parent::boot();
        static::creating(function ($model) {
            $model->uuid    = Str::uuid();
            $model->status  = 'CREATED';
            $model->error_message = null;
        });
     }

    public function getStatusDescriptionAttribute(): string
    {
        return DocumentTestStatusEnum::getDescription($this->status);
    }
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }
    public function software(): BelongsTo
    {
        return $this->belongsTo(Software::class, 'software_id');
    }

    public function documents(): HasMany
    {
        return $this->hasMany(TestDocument::class, 'process_id');
    }
}
