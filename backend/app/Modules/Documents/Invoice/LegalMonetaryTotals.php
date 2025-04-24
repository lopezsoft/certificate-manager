<?php

namespace App\Modules\Documents\Invoice;

use App\Common\DateFunctions;
use Illuminate\Http\Request;

class LegalMonetaryTotals
{
    public static function getPrepaidPayments(Request $request): ?object
    {
        if (!isset($request->prepaid_payments)) {
            return null;
        }
        $prepaid = $request->prepaid_payments;
        if (is_array($prepaid)) {
            $prepaid = (object) $request->prepaid_payments;
        } else if (is_string($request->prepaid_payments)) {
            $prepaid = json_decode($request->prepaid_payments);
        }
        return (object) [
            'id'                => $prepaid->id ?? 0,
            'paid_amount'       => $prepaid->paid_amount ?? 0,
            'paid_date'         => DateFunctions::transformDate($prepaid->paid_date),
            'received_date'     => DateFunctions::transformDate($prepaid->received_date),
            'instruction_id'    => $prepaid->instruction_id ?? "",
        ];
    }
    public static function getTotals(Request $request): object
    {
        $legal_monetary_totals = $request->legal_monetary_totals;
        if (is_array($legal_monetary_totals)) {
            $legal_monetary_totals = (object) $request->legal_monetary_totals;
        } else if (is_string($request->legal_monetary_totals)) {
            $legal_monetary_totals = json_decode($request->legal_monetary_totals);
        }
        return (object) [
            'line_extension_amount'     => $legal_monetary_totals->line_extension_amount ?? 0,  // Total de líneas antes de iva
            'tax_exclusive_amount'      => $legal_monetary_totals->tax_exclusive_amount ?? 0,   // Base gravable de líneas que tienen impuesto, si no tiene impuesto se deja en cero
            'tax_inclusive_amount'      => $legal_monetary_totals->tax_inclusive_amount ?? 0,   // Total de líneas + Impuestos
            'charge_total_amount'       => $legal_monetary_totals->total_charges ?? 0,          // Cargos Globales
            'allowance_total_amount'    => $legal_monetary_totals->total_allowance ?? 0,        // Descuentos Globales
            'pre_paid_amount'           => $legal_monetary_totals->pre_paid_amount ?? 0,
            'payable_amount'            => $legal_monetary_totals->payable_amount ?? 0,
        ];
    }
}
