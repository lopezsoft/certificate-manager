<?php

namespace App\Interfaces;
use Illuminate\Http\Request;

Interface CrudInterface {
    public function create(Request $request);
    public function read(Request $request);
    public function update(Request $request, $id);
    public function delete(Request $request, $id);
}
