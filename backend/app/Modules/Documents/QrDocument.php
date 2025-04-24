<?php

namespace App\Modules\Documents;

use App\Models\Company;
use App\Models\Settings\Software;
use Illuminate\Support\Facades\Storage;
use Milon\Barcode\Facades\DNS2DFacade as DNS2D;

class QrDocument
{
    public  static function getUrl(Software $software, $XmlDocumentKey): string
    {
        $qr = ($software->environment->code == 2) ? "catalogo-vpfe-hab.dian.gov.co" : "catalogo-vpfe.dian.gov.co";
        return "https://{$qr}/document/searchqr?documentkey={$XmlDocumentKey}";
    }
    public static function store($jsonData, Company $company, $shipping): object | null
    {
        $document_number = str_replace('.xml', '', $shipping->XmlDocumentName);
        $img        = "{$company->id}/{$document_number}.png";
        $qrText     = trim($jsonData->qr);
        if(isset($jsonData->qrData)){
            $qrText = base64_decode("{$jsonData->qrData}");
        }
        $qrCode     = base64_decode(DNS2D::getBarcodePNG("{$qrText}", 'QRCODE'));
        $diskName   = 'qr';
        if (!Storage::disk($diskName)->exists($img)) {
            Storage::disk($diskName)->makeDirectory("{$company->id}");
            Storage::disk($diskName)->put("{$img}", $qrCode);
        }
        $data   =  base64_encode($qrCode);
        $url    =  url(Storage::disk($diskName)->url("{$img}"));
        return (object) [
            'qrDian'=> $jsonData->qr,
            "url"   => $url,
            "path" => $img,
            "data"  => $data
        ];
    }
}
