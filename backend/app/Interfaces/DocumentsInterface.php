<?php
namespace App\Interfaces;

use Illuminate\Http\Request;


Interface DocumentsInterface {
    function process(Request $request, $trackId);
}
