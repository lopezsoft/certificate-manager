<?php

namespace App\Exports\Reports;

use App\Common\FunctionsGlobal;
use App\Modules\Documents\Invoice\Customer as CustomerInvoice;
use App\Models\Settings\ReportHeader;
use App\Models\Settings\Resolution;
use App\Models\Taxes\TaxRate;
use App\Models\User;
use App\Modules\Documents\ContentDocument;
use App\Modules\Documents\QrDocument;
use App\MPdf\CustomMPdf;
use Exception;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Lopezsoft\NumbersToLetters\NumbersToLetters;
use Mpdf\MpdfException;

class InvoiceReports
{

    /**
     * @throws MpdfException
     * @throws Exception
     */
    public function getInvoice($params): object | null
    {
        $company    = $params->company;
        $software   = $params->software;
        $trackId    = $params->trackId;
        $shipping   = $params->shipping;

        if (!$shipping) throw new Exception("No hay datos para generar el PDF.");
        $reportHeader   = ReportHeader::query()->where('company_id', $company->id)->first();
        $resolution     = Resolution::query()->find($shipping->resolution_id);
        if (!$resolution) throw new Exception("El documento no tiene una resolución asignada.", 400);
        $jsonData       = $shipping->jsonData;
        if (isset($jsonData->currency) && !isset($jsonData->currencyId)) {
            $currency = (object) $jsonData->currency;
            $jsonData->currencyId = $currency->id;
            $jsonData->currency = $currency;
        }
        $currency = DB::table('currency_sys')
            ->where('company_id', $company->id)
            ->where('currency_id', $jsonData->currencyId)
            ->first();

        if (!$currency) throw new Exception("No existe la moneda ({$currency->plural_name}) asignada al documento.", 400);

        $user           = User::query()->where('id', $shipping->user_id)->first();
        $jsonData->user = $user;
        $signatureDocument = isset($jsonData->signatureDocument) ? (object) $jsonData->signatureDocument  : (object)[
            'cashier' => null,
            'seller' => null,
        ];
        if ($jsonData->customer) {
            $customer                   = $jsonData->customer;
            $customer->typeOrganization = $customer->type_organization ?? $customer->typeOrganization;
            $customer->identityDocument = $customer->identity_document ?? $customer->identityDocument;
            $customer->taxLevel         = $customer->tax_level ?? $customer->taxLevel;
            $customer->taxRegime        = $customer->tax_regime ?? $customer->taxRegime;
            $jsonData->customer         = $customer;
        } else {
            $jsonData->customer = CustomerInvoice::getFinalConsumer();
        }

        $outputName     =  str_replace('.xml', '', $shipping->XmlDocumentName);
        $jsonData->cufe = $trackId;
        $path           = "{$company->id}";

        $qrPath         = $shipping->qrPath ?? ".png";
        if (empty($qrPath)) {
            $qrPath = ".png";
        }
        /**
         * Si no existe el QR lo genera
         */
        if (!Storage::disk('qr')->exists($qrPath)) {
            $qr                 = QrDocument::getUrl($software, $trackId);
            $jsonData->qr       = $qr;
            $qrData             = QrDocument::store($jsonData, $company, $shipping);
            $qrPath             = $qrData->path;
            $shipping->qrPath   = $qrPath;
            $shipping->save();
        }
        $legalMonetaryTotals    = (object) $jsonData->legalMonetaryTotals;
        $model          = new NumbersToLetters();
        $letters        = $model->getNumbersToLetters($legalMonetaryTotals->payable_amount, $currency->plural_name)
            . $currency->denomination;

        $configReport               = ['mode' => 'utf-8', 'format' => 'A4'];
        $configReport['tempDir']    = storage_path('tmp');
        $reportName                 = 'reports.invoice';
        if (in_array($jsonData->typeDocumentId, [4, 5, 15], true)) {
            $reportName     = 'reports.note';
        } else if ($jsonData->typeDocumentId == 11) {
            $reportName     = 'reports.document-support';
        }


        $saleDetail = $jsonData->lines ?? [];

        // --- Paso 1: Obtener todas las cabeceras únicas de extra_data ---
        $allExtraDataItems = collect($saleDetail)
            ->pluck('extra_data') // Extrae solo los arrays 'extra_data'
            ->filter()           // Elimina los nulos o arrays vacíos si alguna línea no tiene extra_data
            ->flatten(1)         // Aplana la colección de arrays en una sola colección de items extra_data
            ->filter();           // Filtra posibles nulos resultantes del aplanamiento

        $uniqueExtraDataHeaders = $allExtraDataItems
            ->pluck('title')     // Extrae solo los 'title'
            ->filter()           // Asegura que el title no sea nulo o vacío
            ->unique()           // Obtiene los títulos únicos
            ->values()           // Reindexa la colección (opcional, pero útil)
            ->all();             // Convierte a un array PHP

        $details            = [];
        $spu                = 0; // Sub Total Price Unit
        $discount           = 0;
        $chargeValue        = 0;
        $totalTax           = 0;
        $retentions         = 0;
        $totalOtherTaxes    = 0;
        $retentionsTaxes    = [];
        $totalLine          = 0;
        foreach ($saleDetail as $line) {
            // Extra data
            $extraDataProcessed = [];
            if (isset($line['extra_data']) && is_array($line['extra_data'])) {
                $extraDataProcessed = collect($line['extra_data'])
                    ->whereNotNull('title')
                    ->mapWithKeys(function ($item) {
                        // Extrae el valor y la alineación (con 'left' por defecto si no se especifica)
                        $title = $item['title'];
                        $value = $item['value'] ?? '';
                        $align = $item['align'] ?? 'left'; // <-- Usa la alineación del dato o 'left'

                        // Guarda un array con ambos datos, usando el title como clave
                        return [$title => ['value' => $value, 'align' => $align]];
                    })
                    ->all();
            }
            // Reemplaza/Añade la versión procesada de extra_data a la línea
            $line['processed_extra_data'] = $extraDataProcessed;
            $pro        = (object) $line;
            $decAmount  = FunctionsGlobal::totalDecimals($pro->invoiced_quantity);
            $decUnit    = FunctionsGlobal::totalDecimals($pro->price_amount);
            $decTotal   = FunctionsGlobal::totalDecimals($pro->total);
            // Totals
            $price      = (float)$pro->price_amount;
            $quantity   = (float)$pro->invoiced_quantity;
            $spu        += ($price * $quantity);
            $pro->discount  = 0;
            $pro->charge    = 0;
            foreach ($pro->tax_totals  ?? [] as $taxLine) {
                $tax = (object) $taxLine;
                $totalTax   += (float)$tax->tax_amount;
                if ((int)$tax->tax_id === 1) {
                    $pro->vat   = $tax->percent;
                }
            }
            foreach ($pro->allowance_charges ?? [] as $chargeValueLine) {
                $charge = (object) $chargeValueLine;
                if (isset($charge->charge_indicator) && ($charge->charge_indicator === 'false')) {
                    $discount       += (float)($charge->amount ?? 0);
                    $pro->discount  = (float)($charge->amount ?? 0);
                } else if (isset($charge->charge_indicator) && ($charge->charge_indicator === 'true')) {
                    $pro->charge    = (float)($charge->amount ?? 0);
                    $chargeValue         = (float)($charge->amount ?? 0);
                }
            }
            $otherTaxes        = collect($pro->tax_totals ?? [])
                ->whereNotIn('tax_id', ['1', '5', '6', '7']);
            foreach ($otherTaxes as $taxLine) {
                $tax = (object) $taxLine;
                $totalOtherTaxes += (float)$tax->tax_amount;
            }
            $taxRetentions     = collect($pro->tax_retentions ?? []);
            foreach ($taxRetentions as $taxLine) {
                $tax = (object) $taxLine;
                $retentions += (float)$tax->tax_amount;
                if ((int)$tax->tax_id === 6) {
                    $pro->rfte   = $tax->percent;
                }
            }
            $line               = $pro;
            $total              = ($price * $quantity) - $pro->discount + $pro->charge;
            $totalLine          += $total;
            $line->detail       = strtoupper(trim((Trim($pro->description) . " " . trim($pro->notes ?? ""))));
            $line->vat          = number_format($pro->vat ?? 0);
            $line->discount     = number_format($pro->discount ?? 0, $decTotal);
            $line->amount       = number_format($pro->invoiced_quantity, $decAmount);
            $line->abbre_unit   = (Trim($pro->mu ?? 'U'));
            $line->unit_price   = "{$jsonData->currency->Symbol} " . number_format($pro->price_amount, $decUnit);
            $line->total        = "{$jsonData->currency->Symbol} " . number_format($total, $decTotal);
            $details[]          = $line;
        }

        $saleTaxes  = [];
        $pdf        = new CustomMPdf($configReport);
        $jsonData->invoice_name = $resolution->invoice_name;
        $jsonData->operationType = DB::table('operation_types')->where('id', $jsonData->operationTypeId ?? $shipping->operation_type_id ?? 1)->first();

        $jsonData->othertax         = $jsonData->othertax ?? 0;
        $jsonData->rfte             = $jsonData->rfte ?? 0;
        $jsonData->rica             = $jsonData->rica ?? 0;
        $jsonData->payment_value    = $jsonData->payment_value ?? 0;
        $jsonData->total            = $legalMonetaryTotals->payable_amount;
        // Charges and discounts
        foreach ($jsonData->allowanceCharges ?? [] as $allowanceChargeL) {
            $allowanceCharge = (object) $allowanceChargeL;
            if (isset($allowanceCharge->charge_indicator) && ($allowanceCharge->charge_indicator === 'false')) {
                $jsonData->totalDiscount    += floatval($allowanceCharge->amount);
            } else {
                $jsonData->totalCharges     += floatval($allowanceCharge->amount);
            }
        }
        // TaxTotals
        foreach ($jsonData->taxTotals ?? [] as $taxL) {
            $tax = (object) $taxL;
            if (isset($tax->tax_subtotal)) {
                foreach ($tax->tax_subtotal as $taxSubtotalL) {
                    $taxSubtotal = (object) $taxSubtotalL;
                    $this->processTax($taxSubtotal, $saleTaxes);
                }
            } else {
                $this->processTax($tax, $saleTaxes);
            }
        }
        // TaxRetentions
        foreach ($jsonData->taxRetentions ?? [] as $taxL) {
            $tax = (object) $taxL;
            if (isset($tax->tax_subtotal)) {
                foreach ($tax->tax_subtotal as $taxSubtotalL) {
                    $taxSubtotal = (object) $taxSubtotalL;
                    $retentions += floatval($taxSubtotal->tax_amount);
                    $this->processTax($taxSubtotal, $retentionsTaxes);
                }
            } else {
                $retentions += floatval($tax->tax_amount);
                $this->processTax($tax, $retentionsTaxes);
            }
        }
        //Discrepancy Response (For only accounting notes)
        if (isset($jsonData->discrepancyResponse)) {
            $jsonData->discrepancyResponse = (object) $jsonData->discrepancyResponse;
        }

        if (isset($jsonData->billingReference)) {
            $jsonData->billingReference = (object) $jsonData->billingReference;
            $jsonData->cude = $jsonData->cufe;
            $jsonData->cufe = $jsonData->billingReference->uuid;
        }
        $settings               = collect($company->settings ?? []);
        $isQuitSignature        = false;
        foreach ($settings as $setting) {
            if ($setting->setting->key_value === 'QUITSIGNATURE') {
                $isQuitSignature = (int)$setting->value === 1;
            }
        }
        $logo                   = str_replace('/storage/', '', $reportHeader->image);
        $data = [
            "logo"              => (Str::length($logo) > 10) ? Storage::disk('public')->url("$logo") : null,
            "headerLine1"       => $reportHeader->line1,
            "headerLine2"       => $reportHeader->line2,
            "saleMaster"        => $jsonData,
            "sale"              => $jsonData,
            "details"           => $details,
            "taxes"             => $saleTaxes,
            "letters"           => $letters,
            "cufe"              => Storage::disk('qr')->url("{$qrPath}"),
            'spu'               => $spu,
            'discount'          => $discount,
            'chargeValue'       => $chargeValue,
            'totalTax'          => $totalTax,
            'totalLine'         => $totalLine,
            "resolution"        => $resolution,
            'totalOtherTaxes'   => $totalOtherTaxes,
            'retentionsTaxes'   => $retentionsTaxes,
            'retentions'        => $retentions,
            'signatureDocument' => $signatureDocument,
            'isFinalConsumer'   => CustomerInvoice::isFinalConsumer($jsonData->customer->dni),
            'isQuitSignature'   => $isQuitSignature,
            'extraDataHeaders'  => $uniqueExtraDataHeaders,
        ];

        $pdf->loadView($reportName, $data);

        $outputName = "{$path}/{$outputName}.pdf";
        $pdf->savePdf($outputName);
        return (object)[
            'path'  => utf8_encode($outputName),
            'url'   => Storage::disk('pdf')->url($outputName),
            'data'  => ContentDocument::getContent("pdf/{$outputName}"),
        ];
    }

    private function processTax($tax, &$saleTaxes): void
    {
        $taxRate    = TaxRate::query()
            ->where('taxe_id', $tax->tax_id)
            ->where('rate_value', $tax->percent)
            ->first();
        if ($taxRate) {
            $saleTaxes[] = (object)[
                'rate_name'     => $taxRate->rate_name,
                'rate_abbre'    => $taxRate->rate_abbre,
                'rate_value'    => $taxRate->rate_value,
                'name_taxe'     => $taxRate->tax->name_taxe,
                'base_amount'   => $tax->taxable_amount,
                'tax_amount'    => $tax->tax_amount,
                'total'         => $tax->tax_amount + $tax->taxable_amount,
                'description'   => $taxRate->tax->description,
            ];
        }
    }
}
