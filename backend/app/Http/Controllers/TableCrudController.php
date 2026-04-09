<?php

namespace App\Http\Controllers;

use App\Services\TableCrudService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TableCrudController extends Controller
{
    /**
     * @OA\Get(
     *     path="/crud",
     *     tags={"CRUD Genérico"},
     *     summary="Listar registros de una tabla",
     *     description="Retorna registros paginados de la tabla indicada por `tbPrefix`. Solo accede a tablas habilitadas para la empresa autenticada.",
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(name="tbPrefix", in="query", required=true, description="Prefijo identificador de la tabla", @OA\Schema(type="string", example="contacts")),
     *     @OA\Parameter(name="where", in="query", description="Filtros en formato JSON serializado", @OA\Schema(type="string")),
     *     @OA\Parameter(name="order", in="query", description="Ordenamiento en formato JSON serializado", @OA\Schema(type="string")),
     *     @OA\Response(
     *         response=200,
     *         description="Lista de registros",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="dataRecords", type="object",
     *                 @OA\Property(property="data", type="array", @OA\Items(type="object"))
     *             )
     *         )
     *     ),
     *     @OA\Response(response=401, description="No autenticado"),
     *     @OA\Response(response=500, description="Tabla no permitida o error interno")
     * )
     *
     * @throws \Exception
     */
    public function index(Request $request): JsonResponse
    {
        $request->uuid = null;
        return TableCrudService::read($request);
    }

    /**
     * @OA\Post(
     *     path="/crud",
     *     tags={"CRUD Genérico"},
     *     summary="Crear registro en una tabla",
     *     description="Inserta un nuevo registro en la tabla indicada por `tbPrefix`. Los datos se envían como JSON serializado en `records`.",
     *     security={{"bearerAuth":{}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"tbPrefix","records"},
     *             @OA\Property(property="tbPrefix", type="string", example="contacts", description="Prefijo identificador de la tabla"),
     *             @OA\Property(property="records", type="string", description="JSON serializado con los campos del nuevo registro")
     *         )
     *     ),
     *     @OA\Response(response=200, description="Registro creado exitosamente", @OA\JsonContent(ref="#/components/schemas/ApiSuccessResponse")),
     *     @OA\Response(response=401, description="No autenticado"),
     *     @OA\Response(response=500, description="Tabla no permitida o error interno")
     * )
     *
     * @throws \Exception
     */
    public function store(Request $request): JsonResponse
    {
        return TableCrudService::create($request);
    }

    /**
     * @OA\Get(
     *     path="/crud/{id}",
     *     tags={"CRUD Genérico"},
     *     summary="Obtener un registro por ID",
     *     description="Retorna un registro específico de la tabla indicada por `tbPrefix`.",
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(name="id", in="path", required=true, description="ID del registro", @OA\Schema(type="integer")),
     *     @OA\Parameter(name="tbPrefix", in="query", required=true, description="Prefijo identificador de la tabla", @OA\Schema(type="string", example="contacts")),
     *     @OA\Response(
     *         response=200,
     *         description="Detalle del registro",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="dataRecords", type="object",
     *                 @OA\Property(property="data", type="array", @OA\Items(type="object"))
     *             )
     *         )
     *     ),
     *     @OA\Response(response=401, description="No autenticado"),
     *     @OA\Response(response=404, description="Registro no encontrado"),
     *     @OA\Response(response=500, description="Tabla no permitida o error interno")
     * )
     *
     * @throws \Exception
     */
    public function show(Request $request, $id): JsonResponse
    {
        $request->uuid = $id;
        return TableCrudService::read($request);
    }

    /**
     * @OA\Put(
     *     path="/crud/{id}",
     *     tags={"CRUD Genérico"},
     *     summary="Actualizar un registro",
     *     description="Actualiza un registro existente en la tabla indicada por `tbPrefix`.",
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(name="id", in="path", required=true, description="ID del registro", @OA\Schema(type="integer")),
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"tbPrefix","records"},
     *             @OA\Property(property="tbPrefix", type="string", example="contacts", description="Prefijo identificador de la tabla"),
     *             @OA\Property(property="records", type="string", description="JSON serializado con los campos a actualizar")
     *         )
     *     ),
     *     @OA\Response(response=200, description="Registro actualizado exitosamente", @OA\JsonContent(ref="#/components/schemas/ApiSuccessResponse")),
     *     @OA\Response(response=401, description="No autenticado"),
     *     @OA\Response(response=404, description="Registro no encontrado"),
     *     @OA\Response(response=500, description="Tabla no permitida o error interno")
     * )
     *
     * @throws \Exception
     */
    public function update(Request $request, $id): JsonResponse
    {
        return TableCrudService::update($request, $id);
    }

    /**
     * @OA\Delete(
     *     path="/crud/{id}",
     *     tags={"CRUD Genérico"},
     *     summary="Eliminar un registro",
     *     description="Elimina un registro de la tabla indicada por `tbPrefix`.",
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(name="id", in="path", required=true, description="ID del registro", @OA\Schema(type="integer")),
     *     @OA\Parameter(name="tbPrefix", in="query", required=true, description="Prefijo identificador de la tabla", @OA\Schema(type="string", example="contacts")),
     *     @OA\Response(response=200, description="Registro eliminado exitosamente", @OA\JsonContent(ref="#/components/schemas/ApiSuccessResponse")),
     *     @OA\Response(response=401, description="No autenticado"),
     *     @OA\Response(response=404, description="Registro no encontrado"),
     *     @OA\Response(response=500, description="Tabla no permitida o error interno")
     * )
     *
     * @throws \Exception
     */
    public function destroy(Request $request, $id): JsonResponse
    {
        return TableCrudService::delete($request, $id);
    }
}
