<?php

namespace App\Queries;

use App\Traits\MessagesTrait;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class UpdateTable
{
    use MessagesTrait;
    public static string $primaryKey  = "id";
    public static function update(Request $request, mixed $fields, string $tb): \Illuminate\Http\JsonResponse
    {
        $ip     = $request->ip();
        try {
            DB::beginTransaction();
            $fieldsTb   = ShowColumns::getColumns($tb); // Listado de las columnas de la tabla
            if (is_array($fields)) {
                foreach ($fields as $value) {
                    self::process($value, $fieldsTb, $tb, $ip);
                }
            }else{
                self::process($fields, $fieldsTb, $tb, $ip);
            }
            DB::commit();
            return self::getResponse();
        } catch (\Exception $e) {
            DB::rollback();
            return self::getResponse500(['error'   => $e->getMessage()]);
        }
    }

    protected  static function process($fields, $fieldsTb, $tb, $ip): void
    {
        $data   = [];
        $pKey   = 0;
        $primaryKey = 'id';
        foreach ($fields as $key => $value) {
            foreach ($fieldsTb as $field) {
                if($field->Key === "PRI"){
                    $primaryKey = $field->Field;
                }
                if($field->Field == $key ){
                    if($field->Type == 'date'){
                        $data[$key] = date('Y-m-d', strtotime(str_replace('/','-',$value)));
                    }else{
                        $data[$key] = $value;
                    }
                    break;
                }
            }
            if ($key == $primaryKey) {
                $primaryKey = $key;
                $pKey       = $value;
            }
        };
        DB::table($tb)
            ->where($primaryKey, $pKey)
            ->limit(1)
            ->update($data);
        AuditTable::audit($ip,$tb,'UPDATE',$data);
    }

}
