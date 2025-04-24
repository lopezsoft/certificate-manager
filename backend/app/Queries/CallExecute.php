<?php

namespace App\Queries;

use Illuminate\Support\Facades\DB;

class CallExecute
{
    public static function execute($callName, $params = []): array
    {
        return DB::select("CALL $callName", $params);
    }
}
