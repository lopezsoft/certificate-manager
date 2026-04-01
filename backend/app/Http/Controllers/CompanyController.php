<?php

namespace App\Http\Controllers;

use App\Common\HttpResponseMessages;
use App\Modules\Company\CompanyQueries;
use App\Queries\CallExecute;
use App\Services\GeneraSettingsService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use App\Models\Company\CompanyModel;

class CompanyController extends Controller
{
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
        return GeneraSettingsService::updateSetting($request);
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
        $company    = CompanyQueries::getCompany();
        CallExecute::execute("sp_create_general_settings(?)", [$company->id]);
        return HttpResponseMessages::getResponse([
            'settings' => $company->settings,
        ]);
    }

    /**
     * @throws \Exception
     */
    public function deleteCustomer($id): JsonResponse
    {
        return CompanyModel::deleteCustomer($id);
    }

    /**
     * @throws \Exception
     */
    public function customers(Request $request): JsonResponse
    {
        return CompanyModel::customers($request);
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
        return CompanyModel::read($request);
    }

    public function update(Request $request, $id): JsonResponse
    {
        return CompanyModel::update($request, $id);
    }
}
