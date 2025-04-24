<?php

namespace App\Models\Settings;

use App\Core\CoreModel;
use App\Models\Environment;
use App\Models\Test\TestProcess;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

/**
 * @method static where(array $whereSend)
 * @method static find($id)
 * @property mixed $type_id
 * @property mixed $integration_type
 * @property mixed $environment
 */
class Software extends CoreModel
{
    /**
     * The table name
    */
    public $table    = 'software_information';
    /**
     * The attributes that are mass assignable.
     *
     * @var array
    */
    protected $with = [
        'environment',
    ];
    protected $appends = [
        'typeDescription',
        'integrationDescription',
    ];
    protected $fillable = [
        'identification', 'pin', 'url','testsetid','environment_id','technical_key', 'type_id',
        'integration_type', 'account_id', 'auth_token', 'initial_number', 'test_process_status'
    ];
    /**
     * The attributes that should be hidden for arrays.
     *
     * @var array
     */
    protected $hidden = [
        'company_id'
    ];
    /**
     * Get the department identification that owns the department.
     */
    public function environment(): BelongsTo
    {
        return $this->belongsTo(Environment::class, 'environment_id');
    }
    public function getIntegrationDescriptionAttribute(): string
    {
        return match ($this->integration_type) {
            2 => 'Proveedor tecnológico',
            default => 'Software propio',
        };
    }
    public function getTypeDescriptionAttribute(): string
    {
        return match ($this->type_id) {
            2 => 'Nómina',
            3 => 'Documento soporte',
            4, 6, 5, 7 => 'D.E P.O.S Electrónico',
            default => 'Facturación',
        };
    }
    public function testProcess(): HasOne
    {
        return $this->hasOne(TestProcess::class, 'software_id')
            ->orderByDesc('id');
    }
}
