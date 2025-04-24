<?php

namespace App\Interfaces\Persons;
use Illuminate\Http\Request;

interface PersonsInterface {
    function createPerson(Request $request);
    function readPerson(Request $request);
    function updatePerson($id, Request $request);
    function deletePerson($id, Request $request);
}
