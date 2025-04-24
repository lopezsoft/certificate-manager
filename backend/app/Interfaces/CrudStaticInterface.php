<?php

namespace App\Interfaces;
use Illuminate\Http\Request;

Interface CrudStaticInterface {
    public static function create(Request $request);
    public static function read(Request $request);
    public static function update(Request $request, $id);
    public static function delete(Request $request, $id);
}
