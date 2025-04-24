<?php

namespace App\Traits;

use App\Models\Company;
use App\Models\Types\TypeDocument;
use DOMDocument;
use Exception;
use Illuminate\Support\Facades\Storage;
use InvalidArgumentException;
use Lopezsoft\UBL21dian\Sign;
use ZipArchive;

/**
 * Document trait.
 */
trait DocumentTrait
{
    protected string $xmlName = '';

    public function getXmlName(): string
    {
        return $this->xmlName;
    }

    public function getPathXML(): string
    {
        return $this->pathXML;
    }

    public function getPathZIP(): string
    {
        return $this->pathZIP;
    }

    public function getAttachmentPathXML(): string
    {
        return $this->attachmentPathXML;
    }

    public function getAttachmentPathZIP(): string
    {
        return $this->attachmentPathZIP;
    }

    public function getAttachmentXmlBase64Bytes(): string
    {
        return $this->attachmentXmlBase64Bytes;
    }

    public function getAttachmentZipBase64Bytes(): string
    {
        return $this->attachmentZipBase64Bytes;
    }

    public function getXmlBase64Bytes(): string
    {
        return $this->XmlBase64Bytes;
    }

    public function getZipBase64Bytes(): string
    {
        return $this->ZipBase64Bytes;
    }
    protected string $pathXML = '';
    protected string $pathZIP = '';
    protected string $attachmentPathXML = '';
    protected string $attachmentPathZIP = '';
    protected string $attachmentXmlBase64Bytes = '';
    protected string $attachmentZipBase64Bytes = '';
    protected string $XmlBase64Bytes = '';
    protected string $ZipBase64Bytes = '';
    /**
     * PPP.
     * Código asignado por la DIAN al PT de tres (3) dígitos.
     */
    public string $ppp = '000';

    public array $paymentFormDefault = [
        'payment_method_id'     => 1,
        'means_payment_id'      => 10,
        'currency_id'           => 272
    ];

    /**
     * Create xml.
     */
    protected function createXML(array $data): DOMDocument
    {
        try {
            $DOMDocumentXML = new DOMDocument();
            $DOMDocumentXML->preserveWhiteSpace = false;
            $DOMDocumentXML->formatOutput = true;
            $DOMDocumentXML->loadXML(view("xml.{$data['typeDocument']['code']}", $data)->render());

            return $DOMDocumentXML;
        } catch (InvalidArgumentException $e) {
            throw new Exception("The API does not support the type of document '{$data['typeDocument']['name']}' Error: {$e->getMessage()}");
        } catch (Exception $e) {
            throw new Exception("Error: {$e->getMessage()}");
        }
    }

    /**
     * @throws Exception
     */
    protected function createDocumentXML(array $data, string $name): DOMDocument
    {
        try {
            $DOMDocumentXML = new DOMDocument();
            $DOMDocumentXML->preserveWhiteSpace = false;
            $DOMDocumentXML->formatOutput = true;
            $DOMDocumentXML->loadXML(view("xml.{$name}", $data)->render());

            return $DOMDocumentXML;
        } catch (InvalidArgumentException $e) {
            throw new Exception("The API does not support the type of document '{$name}' Error: {$e->getMessage()}");
        } catch (Exception $e) {
            throw new Exception("Error: {$e->getMessage()}");
        }
    }

    protected function saveDocument(Company $company, Sign $sign, TypeDocument $typeDocument): string
    {
        $prefix     = $typeDocument->prefix;
        $dir        = "{$company->id}/{$prefix}";
        $nameXML    = $this->getFileName($company, $typeDocument);
        $nameZip    = $this->getFileName($company, $typeDocument, '.zip');
        if (!Storage::has($dir)) {
            Storage::makeDirectory($dir);
        }
        $this->attachmentPathXML    = "{$dir}/{$nameXML}";
        $this->attachmentPathZIP    = "{$dir}/{$nameZip}";
        $storage    = Storage::disk('attachment');
        $xml        = html_entity_decode($sign->xml);
        $storage->put("{$dir}/{$nameXML}", $xml);

        $zip = new ZipArchive();
        $zip->open($storage->path($this->attachmentPathZIP), ZipArchive::CREATE);
        $zip->addFile($storage->path($this->attachmentPathXML), $nameXML);
        $zip->close();

        $this->attachmentXmlBase64Bytes             = base64_encode($storage->get($this->attachmentPathXML));
        return $this->attachmentZipBase64Bytes      = base64_encode($storage->get($this->attachmentPathZIP));
    }

    protected function zipBase64(Company $company, $resolution, Sign $sign): string
    {
        $typeDocument = $resolution->type_document;
        $prefix     = $typeDocument->prefix;
        $nameXML    = $this->getFileName($company, $typeDocument);
        $nameZip    = $this->getFileName($company, $typeDocument, '.zip');

        $this->xmlName  = $nameXML;
        $this->pathXML  = "xml/{$company->id}/{$prefix}/{$nameXML}";
        $this->pathZIP  = "zip/{$company->id}/{$prefix}/{$nameZip}";
        $xmlData        = $sign->xml;
        $putPath        = "xml/{$company->id}/{$prefix}/{$nameXML}";

        Storage::put($putPath, $xmlData);

        $dir        = "zip/{$company->id}/{$prefix}";
        if (!Storage::has($dir)) {
            Storage::makeDirectory($dir);
        }

        $zip = new ZipArchive();
        $zip->open(Storage::path($this->pathZIP), ZipArchive::CREATE);
        $zip->addFile(Storage::path($this->pathXML), $nameXML);
        $zip->close();

        $this->XmlBase64Bytes   = base64_encode(Storage::get($this->pathXML));
        $this->ZipBase64Bytes   = base64_encode(Storage::get($this->pathZIP));
        return $this->ZipBase64Bytes;
    }

    protected function getFileName(Company $company, TypeDocument $typeDocument, $extension = '.xml'): string
    {
        $date   = now();
        $prefix = $typeDocument->prefix;
        $send = $company->send()->firstOrCreate([
            'year' => $date->format('y'),
            'type_document_id' => $typeDocument->id,
        ]);

        if($extension == '.zip'){
            $prefix = "z";
        }
        $name = "{$prefix}{$this->stuffedString($company->dni)}{$this->ppp}{$date->format('y')}{$this->stuffedString($send->next_consecutive ?? 1, 8)}{$extension}";
        $send->increment('next_consecutive');
        return $name;
    }

    /**
     * Stuffed string.
     *
     * @param string $string
     * @param int    $length
     * @param int    $padString
     * @param int    $padType
     *
     * @return string
     */
    static function gstuffedString($string, $length = 10, $padString = 0, $padType = STR_PAD_LEFT): string
    {
        return str_pad($string, $length, $padString, $padType);
    }

    protected function stuffedString($string, $length = 10, $padString = 0, $padType = STR_PAD_LEFT): string
    {
        return str_pad($string, $length, $padString, $padType);
    }

    protected function getZIP(): string
    {
        return $this->ZipBase64Bytes;
    }


    protected function getXML(): string
    {
        return $this->XmlBase64Bytes;
    }


    protected function getNameXML(): string
    {
        return $this->xmlName;
    }


    protected function getAttachmentZIP(): string
    {
        return $this->attachmentZipBase64Bytes;
    }


    protected function getAttachmentXML(): string
    {
        return $this->attachmentXmlBase64Bytes;
    }

    protected function xmlToObject($root)
    {
        $regex = '/.:/';
        $dataXML = [];

        if ($root->hasAttributes()) {
            $attrs = $root->attributes;

            foreach ($attrs as $attr) {
                $dataXML['_attributes'][$attr->name] = $attr->value;
            }
        }

        if ($root->hasChildNodes()) {
            $children = $root->childNodes;

            if (1 == $children->length) {
                $child = $children->item(0);

                if (XML_TEXT_NODE == $child->nodeType) {
                    $dataXML['_value'] = $child->nodeValue;

                    return 1 == count($dataXML) ? $dataXML['_value'] : $dataXML;
                }
            }

            $groups = [];

            foreach ($children as $child) {
                if (!isset($dataXML[preg_replace($regex, '', $child->nodeName)])) {
                    $dataXML[preg_replace($regex, '', $child->nodeName)] = $this->xmlToObject($child);
                } else {
                    if (!isset($groups[preg_replace($regex, '', $child->nodeName)])) {
                        $dataXML[preg_replace($regex, '', $child->nodeName)] = array($dataXML[preg_replace($regex, '', $child->nodeName)]);
                        $groups[preg_replace($regex, '', $child->nodeName)] = 1;
                    }

                    $dataXML[preg_replace($regex, '', $child->nodeName)][] = $this->xmlToObject($child);
                }
            }
        }

        return (object) $dataXML;
    }
}
