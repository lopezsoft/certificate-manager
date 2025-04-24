@if(count($taxes) > 0)
<table class="total-invoice">
    <tr>
        <th>Detalle de impuestos</th>
    </tr>
</table>
<table class="table-footer tb-background-th">
    <thead>
    <tr>
        <th>Tipo</th>
        <th>Base gravable</th>
        <th>Tasa</th>
        <th>Valor impuesto</th>
        <th>Descripción</th>
    </tr>
    </thead>
    <tbody>
    @foreach ($taxes as $key => $tax)
        <tr>
            <td>{{ "{$tax->name_taxe}-{$tax->rate_name}" }}</td>
            <td class="text-right">{{ "{$sale->currency->Symbol} ".number_format($tax->base_amount, 2,".",",") }}</td>
            <td class="text-right">{{ number_format($tax->rate_value,2,".",",")."%" }}</td>
            <td class="text-right">{{ "{$sale->currency->Symbol} ".number_format($tax->tax_amount, 2,".",",") }}</td>
            <td>{{ $tax->description }}</td>
        </tr>
    @endforeach
    </tbody>
</table>
@endif
@if(count($retentionsTaxes) > 0)
    <table class="total-invoice">
        <tr>
            <th>Detalle de retenciones</th>
        </tr>
    </table>
    <table class="table-footer tb-background-th">
        <thead>
        <tr>
            <th>Tipo</th>
            <th>Base gravable</th>
            <th>Tasa</th>
            <th>Valor impuesto</th>
            <th>Descripción</th>
        </tr>
        </thead>
        <tbody>
        @foreach ($retentionsTaxes as $key => $tax)
            <tr>
                <td>{{ "{$tax->name_taxe}-{$tax->rate_name}" }}</td>
                <td class="text-right">{{ "{$sale->currency->Symbol} ".number_format($tax->base_amount, 2,".",",") }}</td>
                <td class="text-right">{{ number_format($tax->rate_value,2,".",",")."%" }}</td>
                <td class="text-right">{{ "{$sale->currency->Symbol} ".number_format($tax->tax_amount, 2,".",",") }}</td>
                <td>{{ $tax->description }}</td>
            </tr>
        @endforeach
        </tbody>
    </table>
@endif
