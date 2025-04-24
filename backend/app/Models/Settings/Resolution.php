<?php

namespace App\Models\Settings;

use App\Core\CoreModel;
use App\Models\Types\TypeDocument;

/**
 * @property mixed $prefix
 * @property mixed $from
 * @property mixed $number
 */
class Resolution extends CoreModel
{

    /**
     * With default model.
     *
     * @var array
     */
    protected $with = [
        'type_document',
    ];

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'type_document_id', 'headerline1', 'headerline2', 'footline1', 'footline2', 'footline3', 'footline4',
        'prefix','range_from','range_up','date_from','date_up','active','resolution_number', 'invoice_name',
        'initial_number', 'state', 'technical_key'
    ];

    /**
     * The attributes that should be hidden for arrays.
     *
     * @var array
     */
    protected $hidden = [
        'company_id',
    ];

    /**
     * The accessors to append to the model's array form.
     *
     * @var array
     */
    protected $appends = [
        'number', 'next_consecutive',
    ];

    /**
     * Get the number.
     *
     * @return mixed
     */
    public function getNumberAttribute(): mixed
    {
        return $this->attributes['number'] ?? $this->from;
    }

    /**
     * Set the resolution line number.
     *
     * @param int $number
     */
    public function setNumberAttribute(int $number): void
    {
        $this->attributes['number'] = $number;
    }

    /**
     * Get the next consecutive.
     *
     * @return string
     */
    public function getNextConsecutiveAttribute(): string
    {
        return "{$this->prefix}{$this->number}";
    }

    /**
     * Get the type document that owns the resolution.
     */
    public function type_document(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(TypeDocument::class);
    }
}
