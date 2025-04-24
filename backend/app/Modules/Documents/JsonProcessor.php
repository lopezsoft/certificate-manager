<?php

namespace App\Modules\Documents;

use App\Models\Company;
use Exception;
use Illuminate\Support\Facades\Storage;
use ZipArchive;

class JsonProcessor
{
    /**
     * @throws Exception
     */
    public static function storeData($documentNumber, Company $company, $jsonData, $typeDocument): object
    {
        $documentNumber = str_replace('.xml', '', $documentNumber);
        $prefix         = $typeDocument->prefix;
        $dir            = "{$company->id}/{$prefix}/{$documentNumber}.json";
        $jsonNameD      = "{$documentNumber}.json";
        try {
            $aws_main_path  = env('AWS_MAIN_PATH', 'test')."/";
            $disk           = Storage::disk('json');
            $disk->put("{$dir}", $jsonData);
            // Comprimir el archivo JSON en zip
            $zip = new ZipArchive();
            $zipName = "{$documentNumber}.zip";
            $zipPath = "{$company->id}/{$prefix}/{$zipName}";

            if ($zip->open($disk->path($zipPath), ZipArchive::CREATE)) {
                $zip->addFromString($jsonNameD, $jsonData);
                $zip->close();
            }
            $disk->delete($dir);
            Storage::cloud()->put("{$aws_main_path}/jsons/{$dir}", $jsonData);
            return (object) [
                'path'    => "{$dir}",
                'name'    => $jsonNameD,
            ];
        }catch(Exception $e){
            throw new Exception($e);
        }
    }
}
