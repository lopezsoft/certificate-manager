<?php

namespace App\Queries;

use App\Traits\MessagesTrait;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class DeleteTable
{
    use MessagesTrait;
    public static function delete(Request $request, $fields, string $tb): \Illuminate\Http\JsonResponse
    {
        try {
            $ip         = $request->ip();
            DB::beginTransaction();
            $data   = [];
            foreach ($fields as $key => $value) {
                $data[$key] = $value;
            }

            $delete  = DB::table($tb)->where($data)->get();

            DB::table($tb)->where($data)->delete();

            AuditTable::audit($ip,$tb,'DELETE',$delete);

            DB::commit();
            return self::getResponse(['records' => $delete]);
        } catch (\Exception $e) {
            DB::rollback();
            return self::getResponse500(['error' => $e->getMessage()]);
        }
    }

}
