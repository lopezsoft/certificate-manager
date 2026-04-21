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
     * GET /v2/admin/quotas — Listar todos los cupos
     */
    public function index(Request $request): JsonResponse
    {
        $quotas = CertificateQuota::with('company', 'assignedBy')
            ->orderByDesc('id')
            ->paginate(20);

        return response()->json(['data' => $quotas]);
    }

    /**
     * POST /v2/admin/quotas — Asignar cupo POSTPAID a empresa
     */
    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'company_id'    => ['required', 'integer', 'exists:companies,id'],
            'quantity'      => ['required', 'integer', 'min:1'],
            'period_start'  => ['required', 'date'],
            'period_end'    => ['required', 'date', 'after:period_start'],
            'notes'         => ['nullable', 'string', 'max:500'],
        ]);

        $quota = $this->quotaService->allocateQuota(
            companyId: $data['company_id'],
            quantity:  $data['quantity'],
            start:     Carbon::parse($data['period_start']),
            end:       Carbon::parse($data['period_end']),
            adminId:   $request->user()->id,
            notes:     $data['notes'] ?? '',
        );

        return response()->json(['data' => $quota->load('company')], 201);
    }

    /**
     * GET /v2/admin/quotas/{id} — Ver detalle de un cupo
     */
    public function show(int $id): JsonResponse
    {
        $quota = CertificateQuota::with('company', 'assignedBy')->findOrFail($id);

        return response()->json(['data' => $quota]);
    }

    /**
     * GET /v2/admin/quotas/company/{id} — Estado de cupos de una empresa
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
