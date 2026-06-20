<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Almacenamiento de certificados (genérico, agnóstico de proveedor)
    |--------------------------------------------------------------------------
    |
    | El almacenamiento de artefactos de certificados es una responsabilidad
    | transversal: NO pertenece a un proveedor concreto. Conviven Viafirma y el
    | otro proveedor (legacy). Por eso la configuración es genérica y el proveedor
    | es solo un segmento de la ruta:
    |
    |   {disk}://{prefix}/certificates/{provider}/{artifact}/{filename}
    |
    | El path por proveedor/artefacto se resuelve en CertificateStoragePathResolver.
    | `prefix` es un nombre libre por entorno (no APP_ENV) para evitar colisiones.
    |
    */

    'storage' => [
        'disk'   => env('CERT_STORAGE_DISK', env('VIAFIRMA_P12_DISK', 'local')),
        'prefix' => env('CERT_STORAGE_PREFIX', 'local'),

        // Disco del proveedor LEGACY (otro proveedor). Históricamente 'attachment'.
        // Al migrar a S3 se cambia a 's3' y, como los archivos se copian a la MISMA
        // ruta relativa, las lecturas siguen funcionando sin reescribir rutas en BD.
        'legacy_disk' => env('CERT_LEGACY_DISK', 'attachment'),

        // Sub-rutas por proveedor/artefacto, relativas a {prefix}/certificates/.
        'paths' => [
            'viafirma_p12' => env('CERT_VIAFIRMA_P12_PATH', 'viafirma/p12'),
            'viafirma_p7b' => env('CERT_VIAFIRMA_P7B_PATH', 'viafirma/p7b'),
        ],
    ],

];
