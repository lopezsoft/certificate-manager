<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Settings\ReportsHeader;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ReportsHeaderController extends Controller
{
    /**
     * @OA\Get(
     *     path="/settings/reports",
     *     tags={"Configuración"},
     *     summary="Obtener configuración de encabezados de reportes",
     *     description="Retorna los parámetros de encabezado utilizados en la generación de reportes y certificados.",
     *     security={{"bearerAuth":{}}},
     *     @OA\Response(
     *         response=200,
     *         description="Configuración de encabezados",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="dataRecords", type="object",
     *                 @OA\Property(property="data", type="array", @OA\Items(type="object"))
     *             )
     *         )
     *     ),
     *     @OA\Response(response=401, description="No autenticado")
     * )
     *
     * @throws \Exception
     */
    public function getData(Request $request): JsonResponse
    {
        return (new ReportsHeader())->getData($request);
    }

    /**
     * @OA\Put(
     *     path="/settings/reports/{id}",
     *     tags={"Configuración"},
     *     summary="Actualizar encabezado de reporte",
     *     description="Actualiza un registro de configuración de encabezado de reporte por su ID.",
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(name="id", in="path", required=true, description="ID del encabezado a actualizar", @OA\Schema(type="integer")),
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             @OA\Property(property="records", type="string", description="JSON serializado con los campos a actualizar")
     *         )
     *     ),
     *     @OA\Response(response=200, description="Encabezado actualizado", @OA\JsonContent(ref="#/components/schemas/ApiSuccessResponse")),
     *     @OA\Response(response=401, description="No autenticado")
     * )
     */
    public function update(Request $request, $id): JsonResponse
    {
        return (new ReportsHeader())->update($request, $id);
    }
}
