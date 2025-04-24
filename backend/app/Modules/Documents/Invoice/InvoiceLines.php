<?php

namespace App\Modules\Documents\Invoice;

use App\Models\DocumentSupport\DsInvoicePeriod;
use App\Models\Invoice\InvoiceLine;
use Illuminate\Http\Request;

class InvoiceLines
{
    public static function getLines(Request $request): \Illuminate\Support\Collection
    {
        $lines          = $request->lines;
        if (is_object($request->lines)) {
            $lines          = (array) $request->lines;
        } elseif (is_string($request->lines)) {
            $lines          = json_decode($request->lines, TRUE);
        }
        $invoiceLines   = collect();
        foreach ($lines as $invoiceLine) {
            $invoiceLine["mu"]      = $invoiceLine["mu"] ?? "U";
            $total                  = $invoiceLine["total"] ?? $invoiceLine["price_amount"] * $invoiceLine["invoiced_quantity"];
            $invoiceLine["total"]   = $total;
            $taxList                = collect($invoiceLine["tax_totals"] ?? []);
            if ($taxList->count() > 0) {
                $taxRetentions          = $taxList->whereIn('tax_id', ['5', '6', '7']);
                $taxTotals              = $taxList->whereNotIn('tax_id', ['5', '6', '7']);
                if ($taxRetentions->count() > 0) {
                    $invoiceLine["tax_retentions"]  = $taxRetentions->toArray();
                }
                if ($taxTotals->count() > 0) {
                    $invoiceLine["tax_totals"]      = $taxTotals->toArray();
                }
            }
            if (isset($invoiceLine["invoice_period"])) {
                $invoicePeriod  = (Object) $invoiceLine["invoice_period"];
                $period         = DsInvoicePeriod::where('id', $invoicePeriod->description_code ?? 1)->first();
                $invoiceLine["invoicePeriod"] = (object) [
                    'StartDate'         => $period->code == 1 ? Date('Y-m-d') : $invoicePeriod->start_date ?? Date('Y-m-d'),
                    'DescriptionCode'   => $period->code,
                    'Description'       => $period->description
                ];
            }
            if (isset($invoiceLine["sellers_item_identification"])) {
                $invoiceLine["sellers_item_identification"] = (Object) $invoiceLine["sellers_item_identification"];
            }
            // mandate
            if (isset($invoiceLine["mandate"])) {
                $invoiceLine["mandate"] = (Object) $invoiceLine["mandate"];
            }
            $invoiceLines->push(new InvoiceLine($invoiceLine));
        }
        return $invoiceLines;
    }
}
