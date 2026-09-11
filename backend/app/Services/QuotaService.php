<?php

namespace App\Services;

use App\Enums\BillingTypeEnum;
use App\Enums\QuotaStatusEnum;
use App\Models\CertificateQuota;
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
     * Verifica si una empresa tiene cupo disponible para una vigencia específica.
     * POSTPAID es flexible (sin restricción de vigencia).
     * PREPAID debe coincidir con la vigencia solicitada.
     */
    public function hasAvailableQuotaForVigencia(int $companyId, int $vigencia): bool
    {
        // Verificar cupo POSTPAID activo (flexible, sin restricción de vigencia)
        $postpaid = CertificateQuota::where('company_id', $companyId)
            ->where('status', QuotaStatusEnum::ACTIVE->value)
            ->where('period_end', '>=', now()->toDateString())
            ->whereRaw('used_quantity < allocated_quantity')
            ->exists();

        if ($postpaid) {
            return true;
        }

        // Verificar items PREPAID pendientes con vigencia específica
        $prepaid = DB::table('certificate_order_items')
            ->join('certificate_orders', 'certificate_orders.id', '=', 'certificate_order_items.certificate_order_id')
            ->where('certificate_orders.company_id', $companyId)
            ->where('certificate_orders.status', 'PAID')
            ->where('certificate_order_items.status', 'PENDING')
            ->where('certificate_order_items.vigencia', $vigencia)
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
     * Consume un cupo disponible para una vigencia específica.
     * POSTPAID es flexible (sin restricción de vigencia).
     * PREPAID debe coincidir con la vigencia solicitada.
     * Operación atómica con lockForUpdate para evitar race conditions.
     *
     * Retorna el ID del item PREPAID consumido (si aplica), o null para POSTPAID.
     *
     * @throws \RuntimeException si no hay cupo para esa vigencia
     * @return int|null ID del item PREPAID consumido, o null si se consumió POSTPAID
     */
    public function consumeQuotaForVigencia(int $companyId, int $vigencia): ?int
    {
        return DB::transaction(function () use ($companyId, $vigencia): ?int {
            // Intentar consumir POSTPAID primero (flexible, sin restricción de vigencia)
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
                    'vigencia'   => $vigencia,
                    'remaining'  => $quota->fresh()->getRemaining(),
                ]);
                return null;  // POSTPAID no tiene item_id
            }

            // Intentar consumir un item PREPAID con vigencia específica
            $item = DB::table('certificate_order_items')
                ->join('certificate_orders', 'certificate_orders.id', '=', 'certificate_order_items.certificate_order_id')
                ->where('certificate_orders.company_id', $companyId)
                ->where('certificate_orders.status', 'PAID')
                ->where('certificate_order_items.status', 'PENDING')
                ->where('certificate_order_items.vigencia', $vigencia)
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
                    'vigencia'   => $vigencia,
                ]);
                return (int) $item->id;  // Retornar ID del item consumido
            }

            throw new \RuntimeException("No se encontró cupo disponible para vigencia de {$vigencia} año(s).");
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
     * Libera el cupo de una solicitud que se CANCELA pero NO se elimina
     * (ej. vencimiento del plazo de verificación KYC — ver
     * CancelExpiredKycRequestUseCase).
     *
     * A diferencia de releaseQuotaForRequest(), actúa sobre el item EXACTO
     * vinculado a esta solicitud en vez de buscar "alguno" del pool de la
     * empresa: la solicitud no se borra, así que el item sigue vinculado por
     * `certificate_request_id` y se puede identificar sin ambigüedad. Esto
     * evita liberar por error el item de otra solicitud (riesgo real del
     * enfoque anterior, que ordenaba por `updated_at` tras desvincular).
     *
     * Equivale a:
     *   UPDATE certificate_order_items
     *   SET certificate_request_id = NULL, status = 'PENDING'
     *   WHERE certificate_request_id = ?
     *
     * Si no hay item PREPAID vinculado (empresa con cupo POSTPAID), cae al
     * decremento de `used_quantity` del periodo vigente.
     *
     * @return bool true si se liberó un cupo, false si no había nada que liberar
     */
    public function releaseQuotaForCancelledRequest(int $certificateRequestId, int $companyId): bool
    {
        return DB::transaction(function () use ($certificateRequestId, $companyId): bool {
            // 1. PREPAID — liberar el item exacto de ESTA solicitud.
            $releasedItems = DB::table('certificate_order_items')
                ->where('certificate_request_id', $certificateRequestId)
                ->update([
                    'certificate_request_id' => null,
                    'status'                 => 'PENDING',
                    'updated_at'             => now(),
                ]);

            if ($releasedItems > 0) {
                Log::info('[QUOTA] Item(s) PREPAID liberado(s) por cancelación de solicitud.', [
                    'certificate_request_id' => $certificateRequestId,
                    'company_id'             => $companyId,
                    'items'                  => $releasedItems,
                ]);

                return true;
            }

            // 2. POSTPAID — no hay item vinculado; devolver cupo del periodo.
            return $this->releasePostpaidQuota($companyId, $certificateRequestId);
        });
    }

    /**
     * Devuelve un cupo POSTPAID del periodo vigente (decrementa
     * `used_quantity` y reactiva el cupo si estaba agotado).
     */
    private function releasePostpaidQuota(int $companyId, ?int $certificateRequestId = null): bool
    {
        $quota = CertificateQuota::where('company_id', $companyId)
            ->whereIn('status', [QuotaStatusEnum::ACTIVE->value, QuotaStatusEnum::EXHAUSTED->value])
            ->where('period_end', '>=', now()->toDateString())
            ->lockForUpdate()
            ->orderByDesc('used_quantity')
            ->first();

        if (!$quota || $quota->used_quantity <= 0) {
            Log::warning('[QUOTA] No se encontró cupo para liberar por cancelación.', [
                'company_id'             => $companyId,
                'certificate_request_id' => $certificateRequestId,
            ]);

            return false;
        }

        $quota->decrement('used_quantity');

        if ($quota->status === QuotaStatusEnum::EXHAUSTED->value) {
            $quota->update(['status' => QuotaStatusEnum::ACTIVE->value]);
        }

        Log::info('[QUOTA] Cupo POSTPAID liberado por cancelación de solicitud.', [
            'company_id'             => $companyId,
            'certificate_request_id' => $certificateRequestId,
            'quota_id'               => $quota->id,
            'remaining'              => $quota->fresh()->getRemaining(),
        ]);

        return true;
    }

    /**
     * Libera un cupo al eliminar una solicitud de certificado.
     *
     * Intenta devolver en orden inverso al consumo:
     *   1. PREPAID → marca el último item USED como PENDING
     *   2. POSTPAID → decrementa used_quantity
     *
     * @return bool true si se liberó un cupo, false si no había nada que liberar
     */
    public function releaseQuotaForRequest(int $companyId): bool
    {
        return DB::transaction(function () use ($companyId): bool {
            // 1. Intentar devolver un item PREPAID (el más reciente USED)
            $item = DB::table('certificate_order_items')
                ->join('certificate_orders', 'certificate_orders.id', '=', 'certificate_order_items.certificate_order_id')
                ->where('certificate_orders.company_id', $companyId)
                ->where('certificate_orders.status', 'PAID')
                ->where('certificate_order_items.status', 'USED')
                ->whereNull('certificate_order_items.certificate_request_id')
                ->lockForUpdate()
                ->select('certificate_order_items.id')
                ->orderByDesc('certificate_order_items.updated_at')
                ->first();

            if ($item) {
                DB::table('certificate_order_items')
                    ->where('id', $item->id)
                    ->update([
                        'status'     => 'PENDING',
                        'updated_at' => now(),
                    ]);

                Log::info('[QUOTA] Item PREPAID liberado por eliminación de solicitud.', [
                    'company_id' => $companyId,
                    'item_id'    => $item->id,
                ]);
                return true;
            }

            // 2. Intentar devolver un cupo POSTPAID
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

                Log::info('[QUOTA] Cupo POSTPAID liberado por eliminación de solicitud.', [
                    'company_id' => $companyId,
                    'quota_id'   => $quota->id,
                    'remaining'  => $quota->fresh()->getRemaining(),
                ]);
                return true;
            }

            Log::warning('[QUOTA] No se encontró cupo para liberar.', ['company_id' => $companyId]);
            return false;
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
     * Retorna el estado de cupos de una empresa, desglosado por vigencia.
     */
    public function getQuotaStatus(int $companyId): array
    {
        $quota = CertificateQuota::where('company_id', $companyId)
            ->where('status', QuotaStatusEnum::ACTIVE->value)
            ->where('period_end', '>=', now()->toDateString())
            ->orderByDesc('id')
            ->first();

        // Contar items PREPAID pendientes por vigencia
        $prepaidByVigencia = DB::table('certificate_order_items')
            ->join('certificate_orders', 'certificate_orders.id', '=', 'certificate_order_items.certificate_order_id')
            ->where('certificate_orders.company_id', $companyId)
            ->where('certificate_orders.status', 'PAID')
            ->where('certificate_order_items.status', 'PENDING')
            ->selectRaw('certificate_order_items.vigencia, COUNT(*) as count')
            ->groupBy('certificate_order_items.vigencia')
            ->get()
            ->keyBy('vigencia');

        $pendingPrepaid1Year = (int) ($prepaidByVigencia->get(1)?->count ?? 0);
        $pendingPrepaid2Year = (int) ($prepaidByVigencia->get(2)?->count ?? 0);
        $totalPendingPrepaid = $pendingPrepaid1Year + $pendingPrepaid2Year;

        return [
            'postpaid' => $quota ? [
                'allocated'  => $quota->allocated_quantity,
                'used'       => $quota->used_quantity,
                'remaining'  => $quota->getRemaining(),
                'expires_at' => $quota->period_end->toDateString(),
                'status'     => $quota->status,
            ] : null,
            'prepaid_items_available' => $totalPendingPrepaid,
            'prepaid_1_year'          => $pendingPrepaid1Year,
            'prepaid_2_year'          => $pendingPrepaid2Year,
            'has_quota'               => $quota !== null || $totalPendingPrepaid > 0,
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
