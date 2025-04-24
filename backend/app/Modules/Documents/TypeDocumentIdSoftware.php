<?php

namespace App\Modules\Documents;

class TypeDocumentIdSoftware
{
    public static function getId(int $documentId = 7): int
    {
        return match ($documentId) {
            11, 15 => 3, // Documento soporte
            13, 14 => 2, // Nómina
            20, 93, 94, 25, 27, 60 => 4, // Documento Equivalente electrónico
            // Documento Equivalente(POS) 25
            // Documento Equivalente(POS) 27
            // Documento Equivalente SPD 60
            default => 1, // Factura
        };
    }
}
