<?php

namespace App\Services\FileSystem;

use App\Interfaces\UploadToS3Contract;
use App\Jobs\S3UploadFileJob;
class UploadXmlFileToS3Service implements UploadToS3Contract
{
    public function upload($params): void
    {
        $localPath          = $params->localPath;
        $aws                = env('AWS_MAIN_PATH', 'test');
        S3UploadFileJob::dispatch($localPath, "{$aws}/{$localPath}");
    }
}
