<?php

namespace App\Interfaces;

use Illuminate\Http\Request;

interface StatusProcessor
{
    public function process(Request $request, $trackId);
}
