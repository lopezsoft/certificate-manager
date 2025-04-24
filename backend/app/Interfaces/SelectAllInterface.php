<?php

namespace App\Interfaces;
use Illuminate\Http\Request;

Interface SelectAllInterface {
    /**
     * Obtener toda la lista de registros en la base de datos
     *
     * @param  mixed $request
     * @return Object
     */
    public function all(Request $request);
}
