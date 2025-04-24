<?php
namespace App\Interfaces;

use Illuminate\Http\Request;

Interface ElectronicDocumentProcessor {
    public function process(Request $request);
}
