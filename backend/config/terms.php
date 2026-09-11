<?php

/*
|--------------------------------------------------------------------------
| Términos y Condiciones MATICERTS
|--------------------------------------------------------------------------
|
| Fuente oficial del documento. `php artisan terms:publish` descarga el
| texto desde aquí, calcula el SHA-256 y guarda el snapshot en BD.
|
*/

return [
    'source_url' => env('TERMS_SOURCE_URL', 'https://maticerts.com/terminos/'),
];
