<?php

namespace App\Queries;

use App\Common\HttpResponseMessages;
use App\Common\MessageExceptionResponse;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class QueryTable
{

    public static function crudQuery(Request $request, Object $params): JsonResponse
    {
        try {
            $primaryKey = 'id';
            $query      = $request->input('query') ?? '';
            $limit      = $request->input('limit') ?? 15;
            $table      = $params->table;
            $where      = $params->where;
            $order      = $params->order;
            $company    = $params->company;
            $company_id = 'company_id';
            $tableQuery = DB::table($table);
            $fields     = ShowColumns::getColumns($table);
            $queryField = '';
            if (strlen($query) > 0) {
                foreach ($fields as $field) {
                    if ($field->Field <> $primaryKey) {
                        $tableQ  = DB::table($table)
                            ->where($field->Field,'like', '%'. $query .'%')
                            ->first();
                        if ($tableQ) {
                            $queryField   = $field->Field;
                            break;
                        }
                    }
                }

                if(strlen($queryField) > 0){
                    $tableQuery->where($queryField, 'like', '%'. $query .'%');
                }
            }
            // where company_id
            foreach ($fields as $field) {
                if ($field->Field == $company_id) {
                    $where    = array_merge($where, [$field->Field => $company->id]);
                }
            }
            $uuid = $request->uuid ?? null;
            if (!is_null($uuid)) {
                $tableQuery->where($primaryKey, $uuid);
            }
            if(count($where) > 0){
                $tableQuery->where($where);
            }

            if(count($order) > 0){
                foreach ($order as $key => $value) {
                    $tableQuery->orderBy($key, $value);
                }
            }

            return HttpResponseMessages::getResponse([
                'dataRecords' => $tableQuery->paginate($limit)
            ]);
        } catch (Exception $e) {
            return HttpResponseMessages::getResponse500([
                'message'   => $e->getMessage()
            ]);
        }
    }

    public static function table(String $table, Array $where = [], $perPage = 15): JsonResponse
    {
        try {
            $query          = DB::table($table);
            if(count($where) > 0) {
                $query->where($where);
            }
            return HttpResponseMessages::getResponse([
                'dataRecords'   => $query->paginate($perPage)
            ]);

        } catch (Exception $e) {
            return HttpResponseMessages::getResponse500([
                'message'   => $e->getMessage()
            ]);
        }
    }
    public static function query(Request $request, String $table = null, $where = [], $order = []): JsonResponse
    {
        try {
            $primaryKey = 'id';
            $query      = $request->input('query') ?? '';
            $limit      = $request->input('limit') ?? 15;
            $tableQuery = DB::table($table);
            if (strlen($query) > 0) {
                $fields     = ShowColumns::getColumns($table);
                $queryField = '';
                foreach ($fields as $field) {
                    if ($field->Field <> $primaryKey) {
                        $tableQ  = DB::table($table)
                                    ->where($field->Field,'like', '%'. $query .'%')
                                    ->first();
                        if ($tableQ) {
                            $queryField   = $field->Field;
                            break;
                        }
                    }
                }

                if(strlen($queryField) > 0){
                    $tableQuery = $tableQuery->where($queryField, 'like', '%'. $query .'%');
                }else {
                    $limit  = 0;
                }
            }

            if(count($where) > 0){
                $tableQuery = $tableQuery->where($where);
            }

            if(count($order) > 0){
                foreach ($order as $key => $value) {
                    $tableQuery = $tableQuery->orderBy($key, $value);
                }
            }

            return HttpResponseMessages::getResponse([
                'dataRecords'   => $tableQuery->paginate($limit),
            ]);
        } catch (Exception $e) {
            return MessageExceptionResponse::response($e);
        }
    }
}
