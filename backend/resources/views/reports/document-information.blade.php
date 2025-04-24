<table class="table-information">
    <tr>
        <th colspan="6" class="text-uppercase text-center">Documento soporte en adquisiciones efectuadas a no obligados a facturar</th>
        <td rowspan="3" class="column-invoice-name">
            <span>{{ $resolution->invoice_name }}</span> <br>
            <span class="color-red">{{ "Nº. {$saleMaster->documentNumber}" }}</span>
        </td>
    </tr>
    <tr>
        <th>Fecha de emisión</th>
        <td>{{ Date("d-m-Y", strtotime($sale->invoiceDate))." ".Date("h:i:s A", strtotime($sale->invoiceTime)) }}</td>
        <th colspan="2">Tipo operación</th>
        <td colspan="2">
            @if(isset($sale->operationType))
                {{ $sale->operationType->name }}
            @endif
        </td>
    </tr>
    <tr>
        <th>Moneda</th>
        <td>{{ "{$sale->currency->Money} ({$sale->currency->CurrencyISO})"}}</td>
        <th>Tasa cambio</th>
        @if(isset($sale->paymentExchangeRate))
            <td>{{ "COP {$sale->paymentExchangeRate['calculation_rate']}"  }}</td>
        @endif
        <th>Fecha tasa</th>
        @if(isset($sale->paymentExchangeRate))
            <td>{{ Date("d-m-Y", strtotime($sale->paymentExchangeRate['date'])) }}</td>
        @endif
    </tr>
</table>
@include('reports.payment-information')
