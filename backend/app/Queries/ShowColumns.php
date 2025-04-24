<?php

namespace App\Queries;

use Illuminate\Support\Facades\DB;

class ShowColumns
{
    public static function getColumns($table = ''): ?array
    {
        if (strlen($table) >0 ) {
            $select = DB::select('SHOW COLUMNS FROM '.$table);
        }else {
            $select = null;
        }

        return $select;
    }
}
