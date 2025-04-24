<table class="table-information">
    <tr>
        <th colspan="2">Fecha de emisión</th>
        <td>{{ Date("d-m-Y", strtotime($sale->invoiceDate))." ".Date("h:i:s A", strtotime($sale->invoiceTime)) }}</td>
        <th colspan="2">Tipo operación</th>
        @if(isset($sale->operationType))
            <td colspan="5"><b>({{$sale->operationType->code}})</b> {{ $sale->operationType->name }}</td>
        @endif
        <td rowspan="2" class="column-invoice-name">
            <span>{{ $resolution->invoice_name }}</span> <br>
            <span class="color-red">{{ "Nº. {$sale->documentNumber}" }}</span>
        </td>
    </tr>
    <tr>
        <th colspan="2">Moneda</th>
        <td>{{ "{$sale->currency->Money} ({$sale->currency->CurrencyISO})"}}</td>
        <th colspan="2">Tasa cambio</th>
        @if(isset($sale->paymentExchangeRate))
            <td>{{ "COP {$sale->paymentExchangeRate->calculation_rate}"  }}</td>
        @endif
        <th>Fecha tasa</th>
        @if(isset($sale->paymentExchangeRate))
            <td>{{ Date("d-m-Y", strtotime($sale->paymentExchangeRate->date)) }}</td>
        @endif
    </tr>
</table>
@include('reports.payment-information')
