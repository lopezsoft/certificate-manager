<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Common\HttpResponseMessages;
use App\Common\MessageExceptionResponse;
use App\Modules\Company\CompanyQueries;
use App\Services\QuotaService;
use Exception;
use Illuminate\Http\JsonResponse;

/**
 * QuotaStatusController — Consulta de estado de cupos para el usuario autenticado.
 *
 * Permite que cualquier usuario autenticado consulte la disponibilidad
 * de certificados de su empresa (POSTPAID + PREPAID).
 *
 * GET /api/v1/quota/status
 */
class QuotaStatusController extends Controller
{
    public function __construct(
        private readonly QuotaService $quotaService,
    ) {}

    /**
     * Retorna el estado de cupo de la empresa del usuario autenticado.
     *
     * @OA\Get(
     *     path="/quota/status",
     *     operationId="quotaStatus",
     *     tags={"Cupos"},
     *     summary="Consultar disponibilidad de certificados",
     *     description="Retorna el estado de cupos de la empresa del usuario autenticado. Incluye cupos POSTPAID (asignados por admin) y PREPAID (comprados vía WOMPI) desglosados por vigencia. El campo 'has_quota' indica si la empresa puede crear nuevas solicitudes.",
     *     security={{"bearerAuth":{}}},
     *     @OA\Response(response=200, description="Estado de cupos obtenido",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Estado de cupos obtenido exitosamente"),
     *             @OA\Property(property="dataRecords", type="object",
     *                 @OA\Property(property="has_quota", type="boolean", example=true, description="true si la empresa puede crear solicitudes"),
     *                 @OA\Property(property="prepaid_items_available", type="integer", example=5, description="Total de certificados PREPAID pendientes de usar"),
     *                 @OA\Property(property="prepaid_1_year", type="integer", example=3, description="Certificados PREPAID de 1 año disponibles"),
     *                 @OA\Property(property="prepaid_2_year", type="integer", example=2, description="Certificados PREPAID de 2 años disponibles"),
     *                 @OA\Property(property="postpaid", type="object", nullable=true,
     *                     @OA\Property(property="allocated", type="integer", example=50),
     *                     @OA\Property(property="used", type="integer", example=12),
     *                     @OA\Property(property="remaining", type="integer", example=38),
     *                     @OA\Property(property="expires_at", type="string", format="date", example="2026-05-31"),
     *                     @OA\Property(property="status", type="string", example="ACTIVE")
     *                 )
     *             )
     *         )
     *     ),
     *     @OA\Response(response=401, description="No autenticado"),
     *     @OA\Response(response=404, description="Empresa no encontrada")
     * )
     */
    public function __invoke(): JsonResponse
    {
        try {
            $company = CompanyQueries::getCompany();
            $status  = $this->quotaService->getQuotaStatus($company->id);

            return HttpResponseMessages::getResponse([
                'message'     => 'Estado de cupos obtenido exitosamente',
                'dataRecords' => $status,
            ]);
        } catch (Exception $e) {
            return MessageExceptionResponse::response($e);
        }
    }
}
