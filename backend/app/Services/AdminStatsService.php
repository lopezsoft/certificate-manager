<?php

namespace App\Services;

use App\Enums\OrderStatusEnum;
use App\Enums\QuotaStatusEnum;
use App\Models\CertificateQuota;
use App\Payments\Enums\PaymentStatusEnum;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * AdminStatsService
 *
 * Estadísticas transversales (todas las empresas) para el administrador:
 *   - Pagos por mes de cada cliente (órdenes PAID, agrupadas por mes de pago).
 *   - Cupos no consumidos (POSTPAID con saldo y items PREPAID pendientes).
 *
 * Solo lectura. Las reglas de consumo de cupo viven en QuotaService.
 */
class AdminStatsService
{
    private const MONTH_NAMES = [
        1 => 'Enero', 2 => 'Febrero', 3 => 'Marzo', 4 => 'Abril',
        5 => 'Mayo', 6 => 'Junio', 7 => 'Julio', 8 => 'Agosto',
        9 => 'Septiembre', 10 => 'Octubre', 11 => 'Noviembre', 12 => 'Diciembre',
    ];

    /**
     * Pagos por mes de cada cliente para un año.
     *
     * La fecha de pago es la de la primera transacción APROBADA de la orden;
     * si no existe (órdenes marcadas PAID por otra vía) se usa updated_at.
     *
     * @return array{year:int, available_years:int[], months:array, companies:array, totals:array}
     */
    public function paymentsByMonth(int $year, ?int $companyId = null): array
    {
        $rows = DB::query()
            ->fromSub($this->paidOrdersQuery($companyId), 'paid')
            ->join('companies', 'companies.id', '=', 'paid.company_id')
            ->whereRaw('YEAR(paid.paid_at) = ?', [$year])
            ->selectRaw('
                paid.company_id,
                companies.company_name,
                companies.dni,
                companies.email,
                companies.has_agreement,
                MONTH(paid.paid_at)      AS nmonth,
                COUNT(*)                 AS orders_count,
                SUM(paid.quantity)       AS certificates_count,
                SUM(paid.subtotal)       AS subtotal,
                SUM(paid.tax_amount)     AS tax_amount,
                SUM(paid.total_amount)   AS total_amount
            ')
            ->groupBy('paid.company_id', 'companies.company_name', 'companies.dni', 'companies.email', 'companies.has_agreement')
            ->groupByRaw('MONTH(paid.paid_at)')
            ->orderBy('companies.company_name')
            ->get();

        $companies = $rows->groupBy('company_id')->map(function (Collection $items) {
            $first  = $items->first();
            $months = [];

            foreach ($items as $row) {
                $months[(int) $row->nmonth] = $this->amountRow($row);
            }

            return [
                'company_id'    => (int) $first->company_id,
                'company_name'  => $first->company_name,
                'dni'           => $first->dni,
                'email'         => $first->email,
                'has_agreement' => (bool) $first->has_agreement,
                'orders'        => (int) $items->sum('orders_count'),
                'certificates'  => (int) $items->sum('certificates_count'),
                'subtotal'      => (float) $items->sum('subtotal'),
                'tax_amount'    => (float) $items->sum('tax_amount'),
                'total_amount'  => (float) $items->sum('total_amount'),
                'months'        => $months,
            ];
        })->sortByDesc('total_amount')->values();

        $months = collect(range(1, 12))->map(function (int $month) use ($rows) {
            $subset = $rows->where('nmonth', $month);

            return [
                'month'        => $month,
                'month_name'   => self::MONTH_NAMES[$month],
                'orders'       => (int) $subset->sum('orders_count'),
                'certificates' => (int) $subset->sum('certificates_count'),
                'subtotal'     => (float) $subset->sum('subtotal'),
                'tax_amount'   => (float) $subset->sum('tax_amount'),
                'total_amount' => (float) $subset->sum('total_amount'),
            ];
        });

        return [
            'year'            => $year,
            'available_years' => $this->availablePaymentYears($companyId),
            'months'          => $months->values()->all(),
            'companies'       => $companies->all(),
            'totals'          => [
                'companies'    => $companies->count(),
                'orders'       => (int) $rows->sum('orders_count'),
                'certificates' => (int) $rows->sum('certificates_count'),
                'subtotal'     => (float) $rows->sum('subtotal'),
                'tax_amount'   => (float) $rows->sum('tax_amount'),
                'total_amount' => (float) $rows->sum('total_amount'),
            ],
        ];
    }

    /**
     * Cupos no consumidos por empresa.
     *
     *   - POSTPAID: cupos con used_quantity < allocated_quantity. Por defecto
     *     solo ACTIVE y vigentes; con $includeExpired también EXPIRED (saldo perdido).
     *   - PREPAID: por cada orden PAID, certificados comprados vs. solicitados
     *     (items USED) vs. pendientes (items PENDING). Ej.: compran 10, solicitan 8,
     *     quedan 2 sin solicitar. En el detalle solo se listan órdenes con pendientes;
     *     los totales por empresa consideran todas sus órdenes pagadas.
     *
     * @return array{companies:array, totals:array}
     */
    public function unusedQuotas(?int $companyId = null, bool $includeExpired = false): array
    {
        $today = now()->toDateString();

        $quotas = CertificateQuota::query()
            ->with(['company', 'pricingTier'])
            ->whereRaw('used_quantity < allocated_quantity')
            ->when(!$includeExpired, fn ($q) => $q
                ->where('status', QuotaStatusEnum::ACTIVE->value)
                ->where('period_end', '>=', $today))
            ->when($companyId, fn ($q) => $q->where('company_id', $companyId))
            ->orderBy('period_end')
            ->get();

        $orders = DB::table('certificate_orders as co')
            ->join('companies', 'companies.id', '=', 'co.company_id')
            ->leftJoin('certificate_order_items as coi', 'coi.certificate_order_id', '=', 'co.id')
            ->where('co.status', OrderStatusEnum::PAID->value)
            ->when($companyId, fn ($q) => $q->where('co.company_id', $companyId))
            ->selectRaw("
                co.company_id,
                companies.company_name,
                companies.dni,
                companies.email,
                companies.has_agreement,
                co.uuid        AS order_uuid,
                co.created_at  AS purchased_at,
                co.vigencia,
                co.quantity    AS purchased,
                SUM(CASE WHEN coi.status = 'USED'    THEN 1 ELSE 0 END) AS requested,
                SUM(CASE WHEN coi.status = 'PENDING' THEN 1 ELSE 0 END) AS pending
            ")
            ->groupBy('co.id', 'co.company_id', 'companies.company_name', 'companies.dni', 'companies.email', 'companies.has_agreement', 'co.uuid', 'co.created_at', 'co.vigencia', 'co.quantity')
            ->orderBy('co.created_at')
            ->get();

        $byCompany = [];

        foreach ($quotas as $quota) {
            $cid = (int) $quota->company_id;
            $byCompany[$cid] ??= $this->emptyCompanyBucket(
                $cid,
                $quota->company?->company_name,
                $quota->company?->dni,
                $quota->company?->email,
                (bool) ($quota->company?->has_agreement ?? false),
            );

            $byCompany[$cid]['postpaid'][] = [
                'quota_id'     => $quota->id,
                'pricing_tier' => $quota->pricingTier?->name,
                'allocated'    => $quota->allocated_quantity,
                'used'         => $quota->used_quantity,
                'remaining'    => $quota->getRemaining(),
                'period_start' => $quota->period_start?->toDateString(),
                'period_end'   => $quota->period_end?->toDateString(),
                'status'       => $quota->status,
                'is_expired'   => $quota->status === QuotaStatusEnum::EXPIRED->value
                                  || ($quota->period_end && $quota->period_end->toDateString() < $today),
                'notes'        => $quota->notes,
            ];
        }

        // Totales PREPAID por empresa (todas las órdenes pagadas)
        $prepaidTotals = [];
        foreach ($orders as $row) {
            $cid = (int) $row->company_id;
            $prepaidTotals[$cid] ??= ['purchased' => 0, 'requested' => 0, 'pending' => 0];
            $prepaidTotals[$cid]['purchased'] += (int) $row->purchased;
            $prepaidTotals[$cid]['requested'] += (int) $row->requested;
            $prepaidTotals[$cid]['pending']   += (int) $row->pending;

            if ((int) $row->pending === 0) {
                continue;
            }

            $byCompany[$cid] ??= $this->emptyCompanyBucket(
                $cid, $row->company_name, $row->dni, $row->email, (bool) $row->has_agreement,
            );

            $byCompany[$cid]['prepaid'][] = [
                'order_uuid'   => $row->order_uuid,
                'purchased_at' => $row->purchased_at,
                'vigencia'     => (int) $row->vigencia,
                'purchased'    => (int) $row->purchased,
                'requested'    => (int) $row->requested,
                'pending'      => (int) $row->pending,
            ];
        }

        $companies = collect($byCompany)->map(function (array $c) use ($prepaidTotals) {
            $totals = $prepaidTotals[$c['company_id']] ?? ['purchased' => 0, 'requested' => 0, 'pending' => 0];

            $c['postpaid_remaining'] = array_sum(array_column($c['postpaid'], 'remaining'));
            $c['prepaid_purchased']  = $totals['purchased'];
            $c['prepaid_requested']  = $totals['requested'];
            $c['prepaid_pending']    = $totals['pending'];
            $c['prepaid_1_year']     = collect($c['prepaid'])->where('vigencia', 1)->sum('pending');
            $c['prepaid_2_year']     = collect($c['prepaid'])->where('vigencia', 2)->sum('pending');
            $c['total_unused']       = $c['postpaid_remaining'] + $c['prepaid_pending'];

            return $c;
        })->sortByDesc('total_unused')->values();

        return [
            'include_expired' => $includeExpired,
            'companies'       => $companies->all(),
            'totals'          => [
                'companies'          => $companies->count(),
                'postpaid_remaining' => (int) $companies->sum('postpaid_remaining'),
                'prepaid_purchased'  => (int) $companies->sum('prepaid_purchased'),
                'prepaid_requested'  => (int) $companies->sum('prepaid_requested'),
                'prepaid_pending'    => (int) $companies->sum('prepaid_pending'),
                'total_unused'       => (int) $companies->sum('total_unused'),
            ],
        ];
    }

    // ─── Privados ──────────────────────────────────────────────────

    /**
     * Sub-consulta de órdenes PAID con su fecha efectiva de pago.
     */
    private function paidOrdersQuery(?int $companyId): Builder
    {
        return DB::table('certificate_orders as co')
            ->where('co.status', OrderStatusEnum::PAID->value)
            ->when($companyId, fn ($q) => $q->where('co.company_id', $companyId))
            ->selectRaw('
                co.id,
                co.company_id,
                co.quantity,
                co.subtotal,
                co.tax_amount,
                co.total_amount,
                COALESCE(
                    (SELECT MIN(pt.paid_at)
                       FROM payment_transactions pt
                      WHERE pt.certificate_order_id = co.id
                        AND pt.status = ?),
                    co.updated_at
                ) AS paid_at
            ', [PaymentStatusEnum::APPROVED->value]);
    }

    /**
     * Años con al menos un pago (para el selector del dashboard).
     *
     * @return int[]
     */
    private function availablePaymentYears(?int $companyId): array
    {
        return DB::query()
            ->fromSub($this->paidOrdersQuery($companyId), 'paid')
            ->selectRaw('DISTINCT YEAR(paid.paid_at) AS y')
            ->orderByDesc('y')
            ->pluck('y')
            ->map(fn ($y) => (int) $y)
            ->all();
    }

    private function amountRow(object $row): array
    {
        return [
            'orders'       => (int) $row->orders_count,
            'certificates' => (int) $row->certificates_count,
            'subtotal'     => (float) $row->subtotal,
            'tax_amount'   => (float) $row->tax_amount,
            'total_amount' => (float) $row->total_amount,
        ];
    }

    private function emptyCompanyBucket(int $id, ?string $name, ?string $dni, ?string $email, bool $hasAgreement): array
    {
        return [
            'company_id'    => $id,
            'company_name'  => $name ?? 'N/A',
            'dni'           => $dni,
            'email'         => $email,
            'has_agreement' => $hasAgreement,
            'postpaid'      => [],
            'prepaid'       => [],
        ];
    }
}
