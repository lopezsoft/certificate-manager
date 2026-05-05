<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Services\CompanyService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CompanyController extends Controller
{
    public function __construct(
        private readonly CompanyService $companyService
    ) {}

    /**
     * @OA\Put(
     *     path="/company/settings",
     *     tags={"Configuración"},
     *     summary="Actualizar configuración general de la empresa",
     *     description="Actualiza los parámetros de configuración general de la empresa autenticada.",
     *     security={{"bearerAuth":{}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             @OA\Property(property="records", type="string", description="JSON serializado con los campos a actualizar")
     *         )
     *     ),
     *     @OA\Response(response=200, description="Configuración actualizada", @OA\JsonContent(ref="#/components/schemas/ApiSuccessResponse")),
     *     @OA\Response(response=401, description="No autenticado")
     * )
     */
    public function updateSetting(Request $request): JsonResponse
    {
        return $this->companyService->updateSetting($request->all());
    }

    /**
     * @OA\Get(
     *     path="/company/settings",
     *     tags={"Configuración"},
     *     summary="Obtener configuración general de la empresa",
     *     description="Retorna la configuración general de la empresa autenticada. Si no existe, la crea automáticamente.",
     *     security={{"bearerAuth":{}}},
     *     @OA\Response(
     *         response=200,
     *         description="Configuración de la empresa",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="settings", type="object")
     *         )
     *     ),
     *     @OA\Response(response=401, description="No autenticado")
     * )
     *
     * @throws \Exception
     */
    public function getSetting(): JsonResponse
    {
        return $this->companyService->getSetting();
    }

    /**
     * @throws \Exception
     */
    public function deleteCustomer(int $id): JsonResponse
    {
        return $this->companyService->deleteCustomer($id);
    }

    /**
     * @throws \Exception
     */
    public function customers(Request $request): JsonResponse
    {
        return $this->companyService->customers($request->input('query'));
    }

    /**
     * @OA\Get(
     *     path="/company",
     *     tags={"Configuración"},
     *     summary="Obtener datos de la empresa autenticada",
     *     description="Retorna la información completa de la empresa del usuario autenticado.",
     *     security={{"bearerAuth":{}}},
     *     @OA\Response(
     *         response=200,
     *         description="Datos de la empresa",
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
    public function read(Request $request): JsonResponse
    {
        $uid = $request->input('uid');
        return $this->companyService->read($uid ? (int) $uid : null);
    }

    public function update(Request $request, int $id): JsonResponse
    {
        return $this->companyService->update($request->all(), $id);
    }
}
