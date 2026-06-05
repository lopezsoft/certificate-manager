<?php

namespace App\Quotas\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Quotas\Models\CertificateQuota;
use App\Quotas\Services\QuotaService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * QuotaController — Sprint 5
 * Solo accesible para administradores de LOPEZSOFT.
 */
class QuotaController extends Controller
{
    public function __construct(
        private readonly QuotaService $quotaService,
    ) {}

    /**
     * GET /admin/quotas — Listar todos los cupos
     *
     * @OA\Get(
     *     path="/admin/quotas",
     *     tags={"Cupos Admin"},
     *     summary="Listar todos los cupos (admin)",
     *     security={{"bearerAuth":{}}},
     *     @OA\Response(response=200, description="Lista paginada de cupos",
     *         @OA\JsonContent(@OA\Property(property="data", type="object"))
     *     ),
     *     @OA\Response(response=403, description="No es administrador")
     * )
     */
    public function index(Request $request): JsonResponse
    {
        $quotas = CertificateQuota::with('company', 'assignedBy')
            ->orderByDesc('id')
            ->paginate(20);

        return response()->json(['data' => $quotas]);
    }

    /**
     * POST /admin/quotas — Asignar cupo POSTPAID a empresa
     *
     * @OA\Post(
     *     path="/admin/quotas",
     *     tags={"Cupos Admin"},
     *     summary="Asignar cupo POSTPAID a una empresa",
     *     security={{"bearerAuth":{}}},
     *     @OA\RequestBody(required=true, @OA\JsonContent(
     *         required={"company_id","quantity","period_start","period_end"},
     *         @OA\Property(property="company_id", type="integer", example=10),
     *         @OA\Property(property="pricing_tier_id", type="integer", nullable=true, example=2, description="Rango de precio asociado (FK a pricing_tiers)"),
     *         @OA\Property(property="quantity", type="integer", minimum=1, example=50),
     *         @OA\Property(property="period_start", type="string", format="date", example="2026-05-01"),
     *         @OA\Property(property="period_end", type="string", format="date", example="2026-05-31"),
     *         @OA\Property(property="notes", type="string", nullable=true, example="Cupo mensual mayo 2026")
     *     )),
     *     @OA\Response(response=201, description="Cupo asignado",
     *         @OA\JsonContent(@OA\Property(property="data", ref="#/components/schemas/CertificateQuota"))
     *     ),
     *     @OA\Response(response=403, description="No es administrador"),
     *     @OA\Response(response=422, description="Error de validación")
     * )
     */
    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'company_id'       => ['required', 'integer'],
            'pricing_tier_id'  => ['nullable', 'integer', 'exists:pricing_tiers,id'],
            'quantity'         => ['required', 'integer', 'min:1'],
            'period_start'     => ['required', 'date'],
            'period_end'       => ['required', 'date', 'after:period_start'],
            'notes'            => ['nullable', 'string', 'max:500'],
        ]);

        $companyId = (int) $data['company_id'];

        // Si company_id = 0, usar la empresa del admin en sesión
        if ($companyId === 0) {
            $companyId = \App\Modules\Company\CompanyQueries::getCompany()->id;
        }

        $quota = $this->quotaService->allocateQuota(
            companyId:     $companyId,
            quantity:      $data['quantity'],
            start:         Carbon::parse($data['period_start']),
            end:           Carbon::parse($data['period_end']),
            adminId:       $request->user()->id,
            notes:         $data['notes'] ?? '',
            pricingTierId: $data['pricing_tier_id'] ?? null,
        );

        return response()->json(['data' => $quota->load('company', 'pricingTier')], 201);
    }

    /**
     * GET /admin/quotas/{id} — Ver detalle de un cupo
     *
     * @OA\Get(
     *     path="/admin/quotas/{id}",
     *     tags={"Cupos Admin"},
     *     summary="Ver detalle de un cupo",
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer"), example=1),
     *     @OA\Response(response=200, description="Detalle del cupo",
     *         @OA\JsonContent(@OA\Property(property="data", ref="#/components/schemas/CertificateQuota"))
     *     ),
     *     @OA\Response(response=404, description="Cupo no encontrado")
     * )
     */
    public function show(int $id): JsonResponse
    {
        $quota = CertificateQuota::with('company', 'assignedBy')->findOrFail($id);

        return response()->json(['data' => $quota]);
    }

    /**
     * GET /admin/quotas/company/{id} — Estado de cupos de una empresa
     *
     * @OA\Get(
     *     path="/admin/quotas/company/{id}",
     *     tags={"Cupos Admin"},
     *     summary="Estado y historial de cupos de una empresa",
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(name="id", in="path", required=true, description="ID de la empresa", @OA\Schema(type="integer"), example=10),
     *     @OA\Response(response=200, description="Estado de cupos",
     *         @OA\JsonContent(@OA\Property(property="data", type="object",
     *             @OA\Property(property="status", type="object",
     *                 @OA\Property(property="allocated", type="integer", example=50),
     *                 @OA\Property(property="used", type="integer", example=12),
     *                 @OA\Property(property="remaining", type="integer", example=38),
     *                 @OA\Property(property="expires_at", type="string", format="date", nullable=true)
     *             ),
     *             @OA\Property(property="history", type="array", @OA\Items(ref="#/components/schemas/CertificateQuota"))
     *         ))
     *     )
     * )
     */
    public function byCompany(int $id): JsonResponse
    {
        $status = $this->quotaService->getQuotaStatus($id);
        $history = CertificateQuota::where('company_id', $id)
            ->orderByDesc('id')
            ->get();

        return response()->json([
            'data' => [
                'status'  => $status,
                'history' => $history,
            ],
        ]);
    }
}
