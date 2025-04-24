<?php

namespace App\Queries;

use App\Common\HttpResponseMessages;
use App\Common\MessageExceptionResponse;
use App\Services\TableValidationService;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class InsertTable
{
    protected static string $primaryKey = 'id';
    public static function insertCrud(Request $request, Object $params, bool $canValidator = true): JsonResponse
    {
        try {
            $fields     = $params->records;
            $tb         = $params->table;
            $ip         = $request->ip();
            // Validar los campos
            if(!is_array($fields) && $canValidator){
                $validator  = new TableValidationService();
                $validator  = $validator->generateValidator((array) $fields, $tb);
                if ($validator->fails()) {
                    return HttpResponseMessages::getResponse400([
                        'message' => $validator->errors()->first()
                    ]);
                }
            }
            DB::beginTransaction();
            $data       = self::getTableData($fields, $tb);
            if (is_array($fields)) {
                $result = DB::table($tb)->insert($data);
            } else {
                $result = DB::table($tb)->insertGetId($data);
            }

            AuditTable::audit($ip,$tb,'INSERT',$data);
            DB::commit();
            $data = DB::table($tb)
                ->where(self::$primaryKey, $result)
                ->first();

            return HttpResponseMessages::getResponse([
                'message' => 'Registro creado correctamente.',
                'payload' => $data
            ]);
        } catch (Exception $e) {
            DB::rollback();
            return HttpResponseMessages::getResponse500([
                "message"   => $e->getMessage()
            ]);
        }
    }
    public static function insert(Request $request, $fields, string $tb): \Illuminate\Http\JsonResponse
    {
        try {
            $ip         = $request->ip();
            $primaryKey = 'id';
            DB::beginTransaction();
            $fieldsTb   = ShowColumns::getColumns($tb); // Listado de las columnas de la tabla
            $data       = [];
            foreach ($fields as $key => $value) {
                if ($key !== $primaryKey) {
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
                }
            }
            $result = DB::table($tb)->insertGetId($data);

            AuditTable::audit($ip,$tb,'INSERT',$data);
            DB::commit();
            $data = DB::table($tb)
                ->where($primaryKey, $result)
                ->first();

            return HttpResponseMessages::getResponse([
                'message' => 'Registro creado correctamente.',
                'record' => $data
            ]);
        } catch (Exception $e) {
            DB::rollback();
            return MessageExceptionResponse::response($e);
        }
    }

    public static function getTableData(mixed $fields, string $tb): array
    {
        $fieldsTb   = ShowColumns::getColumns($tb); // Listado de las columnas de la tabla
        $data       = [];
        if (is_array($fields)) {
            foreach ($fields as $fieldList) {
                $data[] = self::getData($fieldList, $fieldsTb);
            }
        } else {
            $data   = self::getData($fields, $fieldsTb);
        }
        return $data;
    }
    private static function getData($fields, $fieldsTb): array
    {
        $data       = [];
        $primaryKey = 'id';
        foreach ($fields as $key => $value) {
            foreach ($fieldsTb as $field) {
                if($field->Key === "PRI"){
                    $primaryKey = $field->Field;
                }
                if($field->Field == $key && $key !== $primaryKey ){
                    if($field->Type == 'date'){
                        $data[$key] = date('Y-m-d', strtotime(str_replace('/','-',$value)));
                    }else{
                        $data[$key] = $value;
                    }
                    break;
                }
            }
        }
        self::$primaryKey = $primaryKey;
        return $data;
    }
}
