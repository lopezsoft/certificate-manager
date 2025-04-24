@if($saleMaster->discrepancyResponse)
<table class="total-invoice table-customer-header">
    <tr>
        <th>REFERENCIA</th>
    </tr>
</table>
<table class="table-reference">
    <thead>
        <tr>
            <th>Código</th>
            <th>Descripción</th>
            <th>Número factura</th>
            <th>Fecha factura</th>
        </tr>
    </thead>
    <tbody>
        <tr>
            <td>{{ trim($saleMaster->discrepancyResponse->code) }}</td>
            <td>{{ $saleMaster->discrepancyResponse->description }}</td>
            <td>{{ $saleMaster->billingReference->number }}</td>
            <td>{{ Date('d-m-Y', strtotime($saleMaster->billingReference->date)) }}</td>
        </tr>
    </tbody>
</table>
@endif
