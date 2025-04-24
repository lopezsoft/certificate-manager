<?php

namespace App\Interfaces\Persons;
use Illuminate\Http\Request;

interface AllPersonsInterface {
    function allPerson(Request $request);
}
