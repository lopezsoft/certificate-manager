<?php

namespace App\Quotas\Services;

use App\Quotas\Enums\BillingTypeEnum;
use App\Quotas\Enums\QuotaStatusEnum;
use App\Quotas\Models\CertificateQuota;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * QuotaService
 *
 * Gestiona los cupos de certificados por empresa.
 * Solo Admin LOPEZSOFT puede asignar cupos POSTPAID.
 * Todas las operaciones de consumo son atómicas (DB::transaction + lockForUpdate).
 */
class QuotaService
{
    /**
     * Verifica si una empresa tiene cupo disponible (POSTPAID activo o items PREPAID pendientes).
     */
    public function hasAvailableQuota(int $companyId): bool
    {
        // Verificar cupo POSTPAID activo
        $postpaid = CertificateQuota::where('company_id', $companyId)
            ->where('status', QuotaStatusEnum::ACTIVE->value)
            ->where('period_end', '>=', now()->toDateString())
            ->whereRaw('used_quantity < allocated_quantity')
            ->exists();

        if ($postpaid) {
            return true;
        }

        // Verificar items PREPAID pendientes (compra previa con WOMPI)
        $prepaid = DB::table('certificate_order_items')
            ->join('certificate_orders', 'certificate_orders.id', '=', 'certificate_order_items.certificate_order_id')
            ->where('certificate_orders.company_id', $companyId)
            ->where('certificate_orders.status', 'PAID')
            ->where('certificate_order_items.status', 'PENDING')
            ->exists();

        return $prepaid;
    }

    /**
     * Consume un cupo disponible (POSTPAID primero, luego PREPAID).
     * Operación atómica con lockForUpdate para evitar race conditions.
     *
     * @throws \RuntimeException si no hay cupo al momento de consumir
     */
    public function consumeQuota(int $companyId): void
    {
        DB::transaction(function () use ($companyId) {
            // Intentar consumir POSTPAID primero
            $quota = CertificateQuota::where('company_id', $companyId)
                ->where('status', QuotaStatusEnum::ACTIVE->value)
                ->where('period_end', '>=', now()->toDateString())
                ->whereRaw('used_quantity < allocated_quantity')
                ->lockForUpdate()
                ->first();

            if ($quota) {
                $quota->increment('used_quantity');

                // Si el cupo se agotó, cambiar estado
                if ($quota->fresh()->getRemaining() === 0) {
                    $quota->update(['status' => QuotaStatusEnum::EXHAUSTED->value]);
                }

                Log::info('[QUOTA] Cupo POSTPAID consumido.', [
                    'company_id' => $companyId,
                    'quota_id'   => $quota->id,
                    'remaining'  => $quota->fresh()->getRemaining(),
                ]);
                return;
            }

            // Intentar consumir un item PREPAID
            $item = DB::table('certificate_order_items')
                ->join('certificate_orders', 'certificate_orders.id', '=', 'certificate_order_items.certificate_order_id')
                ->where('certificate_orders.company_id', $companyId)
                ->where('certificate_orders.status', 'PAID')
                ->where('certificate_order_items.status', 'PENDING')
                ->lockForUpdate()
                ->select('certificate_order_items.id')
                ->first();

            if ($item) {
                DB::table('certificate_order_items')
                    ->where('id', $item->id)
                    ->update(['status' => 'USED']);

                Log::info('[QUOTA] Item PREPAID consumido.', [
                    'company_id' => $companyId,
                    'item_id'    => $item->id,
                ]);
                return;
            }

            throw new \RuntimeException("No se encontró cupo disponible para la empresa {$companyId}.");
        });
    }

    /**
     * Devuelve un cupo POSTPAID (en caso de error posterior al consumo).
     * Solo se usa en rollback manual.
     */
    public function releaseQuota(int $companyId): void
    {
        DB::transaction(function () use ($companyId) {
            $quota = CertificateQuota::where('company_id', $companyId)
                ->whereIn('status', [QuotaStatusEnum::ACTIVE->value, QuotaStatusEnum::EXHAUSTED->value])
                ->where('period_end', '>=', now()->toDateString())
                ->lockForUpdate()
                ->orderByDesc('used_quantity')
                ->first();

            if ($quota && $quota->used_quantity > 0) {
                $quota->decrement('used_quantity');
                if ($quota->status === QuotaStatusEnum::EXHAUSTED->value) {
                    $quota->update(['status' => QuotaStatusEnum::ACTIVE->value]);
                }
                Log::info('[QUOTA] Cupo devuelto por rollback.', ['company_id' => $companyId]);
            }
        });
    }

    /**
     * Asigna un cupo POSTPAID a una empresa. Solo Admin LOPEZSOFT.
     */
    public function allocateQuota(
        int    $companyId,
        int    $quantity,
        Carbon $start,
        Carbon $end,
        int    $adminId,
        string $notes = '',
        ?int   $pricingTierId = null,
    ): CertificateQuota {
        return CertificateQuota::create([
            'company_id'         => $companyId,
            'pricing_tier_id'    => $pricingTierId,
            'allocated_quantity' => $quantity,
            'used_quantity'      => 0,
            'period_start'       => $start->toDateString(),
            'period_end'         => $end->toDateString(),
            'status'             => QuotaStatusEnum::ACTIVE->value,
            'billing_type'       => BillingTypeEnum::POSTPAID->value,
            'assigned_by'        => $adminId,
            'notes'              => $notes,
        ]);
    }

    /**
     * Retorna el estado de cupos de una empresa.
     */
    public function getQuotaStatus(int $companyId): array
    {
        $quota = CertificateQuota::where('company_id', $companyId)
            ->where('status', QuotaStatusEnum::ACTIVE->value)
            ->where('period_end', '>=', now()->toDateString())
            ->orderByDesc('id')
            ->first();

        $pendingPrepaid = DB::table('certificate_order_items')
            ->join('certificate_orders', 'certificate_orders.id', '=', 'certificate_order_items.certificate_order_id')
            ->where('certificate_orders.company_id', $companyId)
            ->where('certificate_orders.status', 'PAID')
            ->where('certificate_order_items.status', 'PENDING')
            ->count();

        return [
            'postpaid' => $quota ? [
                'allocated'  => $quota->allocated_quantity,
                'used'       => $quota->used_quantity,
                'remaining'  => $quota->getRemaining(),
                'expires_at' => $quota->period_end->toDateString(),
                'status'     => $quota->status,
            ] : null,
            'prepaid_items_available' => $pendingPrepaid,
            'has_quota'               => $quota !== null || $pendingPrepaid > 0,
        ];
    }

    /**
     * Expira cupos cuya fecha period_end ya pasó. Para uso en Scheduled Command.
     *
     * @return int Cantidad de cupos expirados
     */
    public function expireQuotas(): int
    {
        return CertificateQuota::where('status', QuotaStatusEnum::ACTIVE->value)
            ->where('period_end', '<', now()->toDateString())
            ->update(['status' => QuotaStatusEnum::EXPIRED->value]);
    }
}

