<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class JsonData extends Model
{
    // Especifica el nombre de la tabla si es diferente al plural del nombre del modelo
    protected $table = 'json_data';

    // Especifica los campos que se pueden asignar masivamente
    protected $fillable = [
        'shipping_id',
        'jdata',
        'is_migrated',
    ];

    // Define los tipos de datos de los campos
    protected $casts = [
        'jdata' => 'array',
    ];
    // Relación con el modelo ShippingHistory
    public function shippingHistory(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(ShippingHistory::class, 'shipping_id');
    }
}
