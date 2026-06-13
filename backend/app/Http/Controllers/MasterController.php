<?php

namespace App\Http\Controllers;

use App\Services\ReferencedTablesService;
use Illuminate\Http\JsonResponse;

class MasterController extends Controller
{
    public function __construct(
        private readonly ReferencedTablesService $service,
    ) {}

    /**
     * @OA\Get(
     *     path="/organization-type",
     *     tags={"Datos Maestros"},
     *     summary="Listar tipos de organización",
     *     description="Retorna todos los tipos de organización disponibles para el registro de empresas.",
     *     @OA\Response(
     *         response=200,
     *         description="Lista de tipos de organización",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="dataRecords", type="object",
     *                 @OA\Property(property="data", type="array", @OA\Items(
     *                     @OA\Property(property="id", type="integer", example=1),
     *                     @OA\Property(property="name", type="string", example="Sociedad por Acciones Simplificada")
     *                 ))
     *             )
     *         )
     *     )
     * )
     */
    public function getTypeOrganization(): JsonResponse
    {
        return $this->service->getTypeOrganization();
    }

    /**
     * @OA\Get(
     *     path="/identity-documents",
     *     tags={"Datos Maestros"},
     *     summary="Listar tipos de documento de identidad",
     *     description="Retorna los tipos de documento de identidad soportados (Cédula, NIT, RUT, etc.).",
     *     @OA\Response(
     *         response=200,
     *         description="Lista de tipos de documento",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="dataRecords", type="object",
     *                 @OA\Property(property="data", type="array", @OA\Items(
     *                     @OA\Property(property="id", type="integer", example=1),
     *                     @OA\Property(property="code", type="string", example="CC"),
     *                     @OA\Property(property="name", type="string", example="Cédula de Ciudadanía")
     *                 ))
     *             )
     *         )
     *     )
     * )
     */
    public function getIdentityDocuments(): JsonResponse
    {
        return $this->service->getIdentityDocuments();
    }

    /**
     * @OA\Get(
     *     path="/entity-document-types",
     *     tags={"Datos Maestros"},
     *     summary="Listar tipos de documento constitutivo (Cámara de Comercio, etc.)",
     *     description="Retorna los tipos de documento constitutivo soportados, usados en flujos de Persona Jurídica para la validación biométrica.",
     *     @OA\Response(
     *         response=200,
     *         description="Lista de tipos de documento constitutivo",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="dataRecords", type="object",
     *                 @OA\Property(property="data", type="array", @OA\Items(
     *                     @OA\Property(property="id", type="integer", example=1),
     *                     @OA\Property(property="code", type="string", example="CC"),
     *                     @OA\Property(property="description", type="string", example="Cámara de Comercio"),
     *                     @OA\Property(property="active", type="boolean", example=true)
     *                 ))
     *             )
     *         )
     *     )
     * )
     */
    public function getEntityDocumentTypes(): JsonResponse
    {
        return $this->service->getEntityDocumentTypes();
    }
}
