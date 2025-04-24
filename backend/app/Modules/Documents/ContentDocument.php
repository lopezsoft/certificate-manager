<?php

namespace App\Modules\Documents;

use App\Models\Company;
use App\Services\FileSystem\FileSystemService;
use Exception;
use Illuminate\Support\Facades\Storage;

class ContentDocument
{
    /**
     * @throws Exception
     */
    public static function getXmlContent($shipping): null | string
    {
        try {
            $xmlPath            = $shipping->xmlPath;
            $zipPath            = $shipping->zipPath;
            $content            = self::getContent($xmlPath);
            if (!$content) {
                $content        = FileSystemService::extractLocalZip($zipPath);
            }
            if (!$content) {
                $company        = Company::find($shipping->company_id);
                $result         = (new DocumentXml())->getContent($company, $shipping->XmlDocumentKey);
                $content        = $result->XmlBytesBase64 ?? null;
            }
            if(!$content) {
                throw new Exception('No se pudo obtener el contenido del documento');
            }
            return $content;
        }catch (Exception $e) {
            throw new Exception($e->getMessage());
        }
    }
    public static function getPdfContent($shipping): ? object
    {
        try {
            $pdfPath            = "pdf/{$shipping->pdfPath}";
            $content            = self::getContent($pdfPath);
            return (object)[
                'path'  => utf8_encode($shipping->pdfPath),
                'url'   => Storage::disk('pdf')->url($shipping->pdfPath),
                'data'  => $content,
            ];
        }catch (Exception $e) {
            throw new Exception($e->getMessage());
        }
    }
    /**
     * @throws Exception
     */
    public static function getAttachmentContent($shipping): string
    {
        try {
            $attachedPath       = "attachments/{$shipping->attachedPath}";
            $content            = self::getContent($attachedPath);
            return base64_decode($content);
        }catch (Exception $e) {
            throw new Exception($e->getMessage());
        }
    }
    /**
     * @throws Exception
     */
    public static function getAttachmentZipContent($shipping): string
    {
        try {
            $attachedZipPath    = "attachments/{$shipping->attachedZipPath}";
            $content            = self::getContent($attachedZipPath);
            return base64_decode($content);
        }catch (Exception $e) {
            throw new Exception($e->getMessage());
        }
    }
    /**
     * @throws Exception
     */
    public static function getJsonContent($shipping)
    {
        try {
            $jsonPath           = "jsons/{$shipping->jsonPath}";
            $content            = self::getContent($jsonPath);
            return json_decode(base64_decode($content));
        }catch (Exception $e) {
            throw new Exception($e->getMessage());
        }
    }
    /**
     * @throws Exception
     */
    public static function getContent($path): string
    {
        try {
            $content            = FileSystemService::getContentLocal($path);
            if (!$content) {
                $content        = FileSystemService::getContentOfS3($path);
            }
            if(!$content) {
                throw new Exception('No se pudo obtener el contenido del documento');
            }
            return $content;
        }catch (Exception $e) {
            throw new Exception($e->getMessage());
        }
    }
}
