<table class="table-totals">
    <tbody>
    <tr>
        <td rowspan="4" align="center">
            <div class="signature-wrapper">
                <span>{{ $signatureDocument->cashier ?? $signatureDocument->signature_one ?? $saleMaster->user->name }}</span>
                <hr>
                @if($signatureDocument->cashier)
                <div>Cajero(a)</div>
                @else
                <div>Elaboró</div>
                @endif
            </div>
        </td>
        <td rowspan="4" align="center">
            <div class="signature-wrapper">
                <span>{{ $signatureDocument->seller ?? $resolution->signature_two ?? $saleMaster->user->name }}</span>
                <hr>
                @if($signatureDocument->seller)
                <div>Vendedor(a)</div>
                @else
                <div>Aprobó</div>
                @endif
            </div>
        </td>
        <td class="total-text"><b>Subtotal (=)</b></td>
        <td class="total-values text-right border-right"><b>{{ "{$sale->currency->Symbol} ".number_format($spu,2,".",",") }}</b></td>
    </tr>
    <tr>
        <td class="total-text no-bottom-border">Descuento detalle (-)</td>
        <td class="total-values no-bottom-border text-right border-right">{{ "{$sale->currency->Symbol} ".number_format($discount,2,".",",") }}</td>
    </tr>
    <tr>
        <td class="total-text">Recargo detalle (+)</td>
        <td class="total-values text-right border-right">{{ "{$sale->currency->Symbol} ".number_format($chargeValue,2,".",",") }}</td>
    </tr>
    <tr>
        <td class="total-text"><b>Total Bruto (=)</b></td>
        <td class="total-values text-right border-right"><b>{{ "{$sale->currency->Symbol} ".number_format($totalLine,2,".",",") }}</b></td>
    </tr>
    <tr>
        <td class="no-bottom-border">Abono: {{ "" }}</td>
        <td class="no-bottom-border color-red">Saldo: {{ "" }}</td>
        <td class="total-text"><b>Total impuesto (+)</b></td>
        <td class="total-values text-right border-right"><b>{{ "{$sale->currency->Symbol} ".number_format($totalTax, 2,".",",") }}</b></td>
    </tr>
    <tr>
        <td class="total-text">Fecha de Impresión</td>
        <td class="total-values text-right border-right">{{ Date('d-m-y h:i:s A') }}</td>
        <td class="total-text"><b>Total neto (=)</b></td>
        <td class="total-values text-right border-right"><b>{{ "{$sale->currency->Symbol} ".number_format($totalLine + $totalTax, 2,".",",")}}</b></td>
    </tr>
    <tr>
        <td rowspan="7" colspan="2">
            <table class="content-cufe">
                <tr>
                    <td rowspan="2" class="img-qrcode">
                        @if($cufe)
                        <img src="{{ $cufe }}" alt="" class="img-qrcode">
                        @endif
                    </td>
                    <td>
                        {{ strlen($saleMaster->cufe) > 30 ? "CUFE: {$saleMaster->cufe}" : "" }}
                        <br>
                        {{ isset($saleMaster->cude) ? "CUDE: {$saleMaster->cude}" : "" }}
                    </td>
                </tr>
                <tr>
                    <td>{{ $letters }}</td>
                </tr>
            </table>
        </td>
    </tr>
    <tr>
        <td class="total-text no-bottom-border">Descuento Global (-)</td>
        <td class="total-values text-right no-bottom-border border-right">{{ "{$sale->currency->Symbol} ".number_format($saleMaster->totalDiscount ?? 0,2,".",",") }}</td>
    </tr>
    <tr>
        <td class="total-text no-bottom-border">Recargo Global (+)</td>
        <td class="total-values text-right no-bottom-border border-right">{{ "{$sale->currency->Symbol} ".number_format($saleMaster->totalCharges ?? 0,2,".",",") }}</td>
    </tr>
    <tr>
        <td class="total-text">Otros impuestos (+)</td>
        <td class="total-values text-right border-right">{{ "{$sale->currency->Symbol} ".number_format($totalOtherTaxes ?? 0,2,".",",") }}</td>
    </tr>
    <tr>
        <td class="total-text color-green"><b>Valor total (=)</b></td>
        <td class="total-values text-right border-right color-green"><b>{{ "{$sale->currency->Symbol} ".number_format($saleMaster->total, 2,".",",") }}</b></td>
    </tr>
    <tr>
        <td class="total-text color-red">Retenciones (-)</td>
        <td class="total-values text-right border-right color-red">{{ "{$sale->currency->Symbol} ".number_format($retentions ?? 0,2,".",",") }}</td>
    </tr>
    <tr>
        <td class="total-text color-green"><b>Valor total a pagar (=)</b></td>
        <td class="total-values text-right border-right color-green"><b>{{ "{$sale->currency->Symbol} ".number_format($saleMaster->total - $retentions, 2,".",",") }}</b></td>
    </tr>
    </tbody>
</table>
