<?php

namespace App\Http\Controllers;

use App\Services\ConsumeService;

class ConsumeController extends Controller
{
    /**
     * @OA\Get(
     *     path="/consume/{year}",
     *     tags={"Consumo"},
     *     summary="Consumo de documentos por año",
     *     description="Retorna el resumen de documentos procesados para la empresa autenticada durante el año indicado.",
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(name="year", in="path", required=true, description="Año de consulta", @OA\Schema(type="integer", example=2025)),
     *     @OA\Response(
     *         response=200,
     *         description="Resumen de consumo anual",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="dataRecords", type="object",
     *                 @OA\Property(property="data", type="array", @OA\Items(type="object"))
     *             )
     *         )
     *     ),
     *     @OA\Response(response=401, description="No autenticado")
     * )
     */
    public function readByYear($year): \Illuminate\Http\JsonResponse
    {
        return (new ConsumeService())->readByYear($year);
    }

    /**
     * @OA\Get(
     *     path="/consume/{year}/{month}",
     *     tags={"Consumo"},
     *     summary="Consumo de documentos por mes",
     *     description="Retorna el detalle de documentos procesados para la empresa autenticada en el mes y año indicados.",
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(name="year", in="path", required=true, description="Año de consulta", @OA\Schema(type="integer", example=2025)),
     *     @OA\Parameter(name="month", in="path", required=true, description="Mes de consulta (1-12)", @OA\Schema(type="integer", example=6)),
     *     @OA\Response(
     *         response=200,
     *         description="Detalle de consumo mensual",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="dataRecords", type="object",
     *                 @OA\Property(property="data", type="array", @OA\Items(type="object"))
     *             )
     *         )
     *     ),
     *     @OA\Response(response=401, description="No autenticado")
     * )
     */
    public function readByMonth($year, $month): \Illuminate\Http\JsonResponse
    {
        return (new ConsumeService())->readByMonth($year, $month);
    }
}
