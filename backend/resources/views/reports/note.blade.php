<?php
    $count  = 0;
?>
<!DOCTYPE html>
<html>
	<head>
		<meta http-equiv="Content-Type" content="text/html; charset=UTF-8" />
        <link rel="stylesheet" type="text/css" href="{{ asset('assets/css/invoice.css') }}" >
	</head>
	<body>
        @include('reports.header')
        @include('reports.invoice-information')
        {{-- Customer data --}}
        <table class="total-invoice table-customer-header">
            <tr>
                <th>Datos del adquiriente</th>
            </tr>
        </table>
        @include('reports.customer-data')
        {{-- Reference --}}
        @include('reports.reference-note')
        {{-- Detalle  --}}
        @include('reports.detail')
        {{-- Totales --}}
        @include('reports.totals')
        {{-- Footer --}}
        @if (strlen($saleMaster->notes) > 0)
        <table class="table-footer">
            <tr>
                <td>
                    Observaciones: {{ $saleMaster->notes }}
                </td>
            </tr>
        </table>
        @endif
        @include('reports.footer')
	</body>
</html>
