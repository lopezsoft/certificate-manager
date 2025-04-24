<?php

namespace App\Interfaces;

use App\Models\Company;

interface UploadToS3Contract
{
    public function upload(Object $params): void;
}
