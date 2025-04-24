<?php

namespace App\Services;

use Illuminate\Support\Facades\Storage;

class PdfDocumentService
{
    public static function isExists($shipping): bool
    {        $path   = $shipping->pdfPath ?? '.pdf';

        if (is_null($path)) {
            $path = '.pdf';
        };
        $exists = (Storage::disk('pdf')->exists($path));
        if (!$exists) {
            $aws_main_path  = env('AWS_MAIN_PATH', 'test');
            $path           = "{$aws_main_path}/{$shipping->pdfPath}";
            $exists         = Storage::cloud()->exists($path);
        }
        return $exists;
    }
}
