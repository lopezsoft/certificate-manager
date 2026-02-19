<?php

namespace App\Http\Controllers;

use App\Services\LocationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class LocationController extends Controller
{
    /**
     * @OA\Get(
     *     path="/countries",
     *     tags={"Datos Maestros"},
     *     summary="Listar países activos",
     *     description="Retorna todos los países activos disponibles en el sistema.",
     *     @OA\Response(
     *         response=200,
     *         description="Lista de países",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="dataRecords", type="object",
     *                 @OA\Property(property="data", type="array", @OA\Items(
     *                     @OA\Property(property="id", type="integer", example=1),
     *                     @OA\Property(property="name", type="string", example="Colombia")
     *                 ))
     *             )
     *         )
     *     )
     * )
     */
    public function getCountries(): JsonResponse
    {
        return LocationService::getCountries();
    }

    /**
     * @OA\Get(
     *     path="/departments",
     *     tags={"Datos Maestros"},
     *     summary="Listar departamentos",
     *     description="Retorna todos los departamentos disponibles en el sistema.",
     *     @OA\Response(
     *         response=200,
     *         description="Lista de departamentos",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="dataRecords", type="object",
     *                 @OA\Property(property="data", type="array", @OA\Items(
     *                     @OA\Property(property="id", type="integer", example=5),
     *                     @OA\Property(property="name", type="string", example="Antioquia")
     *                 ))
     *             )
     *         )
     *     )
     * )
     */
    public function getDepartments(): JsonResponse
    {
       return LocationService::getDepartments();
    }

    /**
     * @OA\Get(
     *     path="/cities",
     *     tags={"Datos Maestros"},
     *     summary="Listar ciudades",
     *     description="Retorna ciudades con sus códigos postales. Soporta búsqueda por nombre o código de ciudad.",
     *     @OA\Parameter(name="query", in="query", description="Búsqueda por nombre o código de ciudad", @OA\Schema(type="string", example="Medellín")),
     *     @OA\Parameter(name="code", in="query", description="Filtrar por código de ciudad exacto", @OA\Schema(type="string", example="05001")),
     *     @OA\Response(
     *         response=200,
     *         description="Lista de ciudades",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="dataRecords", type="object",
     *                 @OA\Property(property="data", type="array", @OA\Items(
     *                     @OA\Property(property="id", type="integer", example=149),
     *                     @OA\Property(property="name_city", type="string", example="Medellín"),
     *                     @OA\Property(property="city_code", type="string", example="05001"),
     *                     @OA\Property(property="postalCode", type="object")
     *                 ))
     *             )
     *         )
     *     )
     * )
     */
    public function getCities(Request $request): JsonResponse
    {
        return LocationService::getCities($request);
    }
}
