<?php

namespace App\Models;

use App\Core\CoreModel;

/**
 * Tipo de documento constitutivo de entidad.
 *
 * Determina el flujo de verificación Viafirma para personas jurídicas:
 * - Cámara de Comercio (id=1) → enlace biométrico instantáneo
 * - Personería Jurídica (id=2) → contacto por email
 *
 * @property int    $id
 * @property string $code
 * @property string $description
 * @property bool   $active
 */
class EntityDocumentType extends CoreModel
{
    public $table = 'entity_document_types';
    public $timestamps = false;

    protected $fillable = [
        'code',
        'description',
        'active',
    ];

    protected $casts = [
        'active' => 'boolean',
    ];
}
