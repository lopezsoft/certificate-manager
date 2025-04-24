<?php

namespace App\Services;

use App\Models\ShippingHistory;
use Carbon\Carbon;
use Exception;
use Illuminate\Support\Facades\DB;

class CorrectionShippingService
{
    /**
     * @throws Exception
     */
    public static function  validStatus(): void
    {
        try {
            // Calcula la fecha y hora de hace 48 horas
            $limitDateTime = Carbon::now()->subHours(48);

            $query = DB::table('shipping_history as a')
                ->select('a.id')
                ->where('a.is_valid', '=', 0)
                ->where('a.updated_at', '>=', $limitDateTime) // NUEVA RESTRICCIÓN: Creado en las últimas 36 horas
                ->whereNotNull('a.XmlDocumentKey')
                ->whereNotIn('a.type_document_id', [13, 14])
                ->whereNotExists(function ($subQuery) {
                    $subQuery->select(DB::raw(1))
                        ->from('shipping_history as sh_inner')
                        ->where('sh_inner.is_valid', '=', 2)
                        ->whereColumn('sh_inner.id', 'a.id');
                })
                ->orderBy('a.invoice_date', 'desc')
                ->limit(500);

            $dataRecords = $query->get();
            foreach ($dataRecords as $dataRecord) {
                DB::table('shipping_history')
                    ->where('id', $dataRecord->id)
                    ->update([
                        'is_valid' => 2,
                    ]);
            }
        } catch (Exception $e) {
            throw new Exception($e);
        }
    }
    /**
     * @throws Exception
     */
    public static function correctionData(): void
    {
        try {
            $query      = ShippingHistory::query()
                ->where('payable_amount', '=', 0)
                ->orWhereNull('invoice_date')
                ->limit(300);
            $dataRecords = $query->get();
            foreach ($dataRecords as $dataRecord) {
                $jsonDocument = $dataRecord->getJsonDataAttribute();
                if ($jsonDocument) {
                    $payable_amount = $jsonDocument->totalPayment ?? $jsonDocument->totalAmount ?? 0;
                    $invoice_date   = $jsonDocument->invoiceDate ?? $jsonDocument->date ?? null;
                    DB::table('shipping_history')
                        ->where('id', $dataRecord->id)
                        ->limit(1)
                        ->update([
                            'payable_amount' => $payable_amount,
                            'invoice_date'   => $invoice_date,
                        ]);
                }
            }
        } catch (Exception $e) {
            throw new Exception($e);
        }
    }
}
