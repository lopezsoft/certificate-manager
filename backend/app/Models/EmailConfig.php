<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Database\Eloquent\Model;

class EmailConfig extends Model
{
    use HasFactory;

    protected $fillable = [
        'company_id',
        'driver',
        'host',
        'port',
        'encryption',
        'username',
        'password',
        'from_address',
        'from_name',
        'active',
    ];

    // Mutador para encriptar la contraseña al establecerla
    public function setPasswordAttribute($value): void
    {
        $this->attributes['password'] = Crypt::encryptString($value);
    }

    // Accessor para desencriptar la contraseña al obtenerla
    public function getPasswordAttribute($value): string
    {
        return Crypt::decryptString($value);
    }
}
