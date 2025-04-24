<?php

namespace App\MPdf;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\View;
use Mpdf\Mpdf;
use Mpdf\MpdfException;

class CustomMPdf extends Mpdf
{
    /**
     * @throws MpdfException
     */
    public function save($path): void
    {
        Storage::put($path, $this->Output('file.pdf', 'S'));
    }

    public function savePdf($path): void
    {
        Storage::disk('pdf')->put($path, $this->Output('file.pdf', 'S'));
    }
    /**
     * Load a View and convert to HTML
     * @throws MpdfException
     */
    public function loadView($view, array $data = [], array $mergeData = [], $encoding = null): void
    {
        $this->WriteHTML(View::make($view, $data, $mergeData)->render());
    }
}
