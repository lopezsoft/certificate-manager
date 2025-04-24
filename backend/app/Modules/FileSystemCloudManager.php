<?php

namespace App\Modules;

use Exception;
use Illuminate\Support\Facades\Storage;

class FileSystemCloudManager
{
    /**
     * @throws Exception
     */
    public static function put(string $path, mixed $content): string
    {
        try {
            $mPath          = env('FILESYSTEM_MAIN_PATH', 'test');
            $path           = "{$mPath}/{$path}";
            $disk           = Storage::cloud();
            $disk->put("{$path}", $content, ['visibility' => 'public']);
            return $disk->url($path);
        }catch (Exception $e) {
            throw new Exception($e->getMessage());
        }
    }
}
