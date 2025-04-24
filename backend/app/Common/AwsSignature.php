<?php

namespace App\Common;

use Illuminate\Support\Facades\Storage;

class AwsSignature
{
    public static function generatePresignedUrl($filePath): string
    {
        $client = Storage::cloud()->getClient();
        $expiry = now()->addDay(); // 1 day

        $command = $client->getCommand('GetObject', [
            'Bucket' => env('AWS_BUCKET'),
            'Key'    => $filePath
        ]);

        return (string) $client->createPresignedRequest($command, $expiry)->getUri();
    }
}

