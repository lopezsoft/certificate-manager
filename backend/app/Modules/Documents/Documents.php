<?php

namespace App\Modules\Documents;

use App\Interfaces\DocumentsInterface;
use Illuminate\Http\Request;

class Documents
{
    static function processDocuments(Request $request, $trackId, DocumentsInterface $document) {
        return $document->process($request, $trackId);
    }
}
