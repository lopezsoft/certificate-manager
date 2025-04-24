<?php

namespace App\Services\FileSystem;
use App\Interfaces\UploadToS3Contract;
use Exception;
use Illuminate\Support\Facades\Storage;

class FileSystemService
{
    public static function uploadToS3(UploadToS3Contract $contract, Object $params): void
    {
        $contract->upload($params);
    }

    /**
     * @throws Exception
     */
    public static function getContentOfS3($path): null | string
    {
        try {
            $aws_main_path  = env('AWS_MAIN_PATH', 'test');
            $path           = "{$aws_main_path}/{$path}";
            if(!Storage::cloud()->exists($path)){
                return null;
            }
            return base64_encode(Storage::cloud()->get($path));
        }catch (Exception $e) {
            throw new Exception($e->getMessage());
        }
    }
    /**
     * @throws Exception
     */
    public static function getContentLocal($path): null | string
    {
        try {
            if(!Storage::exists($path)){
                return null;
            }
            return base64_encode(Storage::get($path));
        }catch (Exception $e) {
            throw new Exception($e->getMessage());
        }
    }

    /**
     * @throws Exception
     */
    public static function extractLocalZip($path): null | string
    {
        try {
            if (!Storage::exists($path)) {
                return null;
            }
            $zip = new \ZipArchive();
            $extractTime = now()->format('YmdHis');
            $extractedPath = storage_path("app/extracted/{$extractTime}");
            $zip->open($path);
            $zip->extractTo(storage_path($extractedPath));
            $zip->close();
            $content = base64_encode(Storage::get("extracted/{$extractTime}/{$zip->getNameIndex(0)}"));
            Storage::deleteDirectory("extracted/{$extractTime}");
            return $content;
        }catch (Exception $e) {
            throw new Exception($e->getMessage());
        }
    }
    public static function deleteS3($path): void
    {
        $aws_main_path  = env('AWS_MAIN_PATH', 'test');
        $path           = "{$aws_main_path}/{$path}";
        Storage::cloud()->delete($path);
    }
    /**
     * @throws Exception
     */
    public static function deleteLocal($path): void
    {
        try {
            if (!Storage::exists($path)) {
                throw new Exception("El archivo {$path} no existe");
            }
            Storage::delete($path);
        }catch (Exception $e) {
            throw new Exception($e->getMessage());
        }
    }
}
