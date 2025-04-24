<?php

namespace App\Queries;

use App\Traits\MessagesTrait;
use Illuminate\Support\Facades\DB;

class TableQueries
{
    use MessagesTrait;
    public static function getData(QueryParams $queryParams): \Illuminate\Http\JsonResponse
    {
        $query  = DB::table($queryParams->table);
        if (isset($queryParams->where) && count($queryParams->where) > 0) {
           $query   = $query->where($queryParams->where);
        }
        $data   = $query->get();
        return self::getResponse([
            'records'   => $data,
            'total'     => count($data)
        ]);
    }
}
