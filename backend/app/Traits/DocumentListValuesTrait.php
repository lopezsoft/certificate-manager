<?php

namespace App\Traits;

trait DocumentListValuesTrait
{
    protected array $invoiceListValues = [
        7, // Factura de Venta Electrónica Nacional
        8, // Factura de Venta Electrónica Internacional
    ];

    protected array $documentsPDFList   = [
        1, 3, 4
    ];
}
