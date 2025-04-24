<?php

namespace App\Models\Invoice;

use Illuminate\Database\Eloquent\Model;

class BillingReference extends Model
{
    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'number', 'uuid', 'date', 'scheme_name'
    ];

    /**
     * The accessors to append to the model's array form.
     *
     * @var array
     */
    protected $appends = [
        'scheme_name',
    ];

    /**
     * Set the number allowance billing reference.
     *
     * @param string $value
     */
    public function setNumberAttribute($data)
    {
        return $this->attributes['number'] = $data;
    }

    /**
     * Get the numer billing reference.
     *
     * @return string
     */
    public function getNumberAttribute()
    {
        return $this->attributes['number'] ?? [];
    }

    /**
     * Set the scheme name billing reference.
     *
     * @return string
     */
    public function setSchemeNameAttribute($data)
    {
        return $this->attributes['scheme_name'] = $data;
    }

    /**
     * Get the scheme name billing reference.
     *
     * @return string
     */
    public function getSchemeNameAttribute(): string
    {
        return $this->attributes['scheme_name'] ?? 'CUFE-SHA384';
    }

    /**
     * Set the uuid allowance billing reference.
     *
     * @param string $value
     */
    public function setUuidAttribute($data)
    {
        return $this->attributes['uuid'] = $data;
    }

    /**
     * Get the uuid billing reference.
     *
     * @return string
     */
    public function getUuidAttribute(): array|string
    {
        return $this->attributes['uuid'] ?? [];
    }

    /**
     * Set the issue date allowance billing reference.
     *
     * @param string $value
     */
    public function setIssueDateAttribute($data)
    {
        return $this->attributes['date'] = $data;
    }

    /**
     * Get the issue date billing reference.
     *
     * @return string
     */
    public function getIssueDateAttribute(): array|string
    {
        return $this->attributes['date'] ?? [];
    }
}
