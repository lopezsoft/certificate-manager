<!DOCTYPE html>
<html>
	<head>
		<meta http-equiv="Content-Type" content="text/html; charset=UTF-8" />
        <link rel="stylesheet" type="text/css" href="{{ asset('assets/css/pos.css') }}" >
	</head>
	<body>
		<header>
			<table>
				@if (isset($logo))
				<tr>
					<th>
						<img src="{{ $logo }}">
					</th>
				</tr>
				@endif
				@if (isset($headerLine1))
				<tr>
					<th>
						<div>
							{!! $headerLine1 !!}
						</div>
					</th>
				</tr>
				@endif
				@if (isset($headerLine2))
				<tr>
					<th>
						{!! $headerLine2 !!}
					</th>
				</tr>
				@endif
			</table>
		</header>
        <hr>
        <table>
            <tr>
                <th>Fecha:</th>
                <td>{{ date('d/m/Y',strtotime($saleMaster->invoice_date)) }}</td>
                <th>Hora:</th>
                <td>{{ date('h:i:s A',strtotime($saleMaster->invoice_time)) }}</td>
            </tr>
        </table>
        <table>
            <tr>
                <th>{{ "{$saleMaster->invoice_name} Nº." }}</th>
                <td>{{ "{$saleMaster->prefix}{$saleMaster->invoice_nro}" }}</td>
            </tr>
        </table>
        <table>
            <tr>
                <th>CLIENTE:</th>
                <td>{{ $saleMaster->company_name }}</td>
            </tr>
        </table>
        <table>
            <tr>
                <th>CAJERO(A):</th>
                <td>{{ $saleMaster->username }}</td>
            </tr>
        </table>
        <hr>
        <table class="table-detail">
            <tr>
                <td class="code">CÓDIGO</td>
                <td class="amount">CANT</td>
                <td class="unit">U.M</td>
                <td class="price">PRECIO</td>
                <td class="total">TOTAL</td>
            </tr>
            @foreach ($details as $line)
            <tr>
                <td  colspan="5" class="detail">
                    {{ "({$line->rate_abbre}) ".$line->detail }}
                </td>
            </tr>
            <tr>
                <td class="code">
                    {{ $line->sku ?? $line->internal_code }}
                </td>
                <td class="amount">
                    {{ $line->amount }}
                </td>
                <td class="unit">
                    {{ $line->abbre_unit }}
                </td>
                <td class="price">
                    {{ $line->unit_price}}
                </td>
                <td class="total">
                    {{ $line->total}}
                </td>
            </tr>
            @endforeach
        </table>
        <hr>
        <table class="total-invoice">
            <tr>
                <td>Totales de la factura</td>
            </tr>
        </table>
        <hr>
        <table class="total-details">
            <tr>
                <td id="left">
                    SUBTOTAL
                </td>
                <td id="right">{{ "{$saleMaster->Symbol} ".number_format($saleMaster->subtotal,2,".",",") }}</td>
            </tr>
            <tr>
                <td id="left">
                    DESCUENTOS
                </td>
                <td id="right">{{ "{$saleMaster->Symbol} ".number_format($saleMaster->discount,2,".",",") }}</td>
            </tr>
            <tr>
                <td id="left">
                    IVA
                </td>
                <td id="right">{{ "{$saleMaster->Symbol} ".number_format($saleMaster->tax_value,2,".",",") }}</td>
            </tr>
            <tr>
                <td id="left">
                    TOTAL
                </td>
                <td id="right">{{ "{$saleMaster->Symbol} ".number_format($saleMaster->total,2,".",",") }}</td>
            </tr>
        </table>
        <hr>
        <table class="total-invoice">
            <tr>
                <td>Formas de pago</td>
            </tr>
        </table>
        <hr>
        <table class="total-details">
            <tr>
                <td id="left">
                    {{ strtoupper($saleMaster->means_name) }}
                </td>
                <td id="right">{{ "{$saleMaster->Symbol} ".number_format($saleMaster->cash,2,".",",") }}</td>
            </tr>
            <tr>
                <td id="left">
                    CAMBIO
                </td>
                <td id="right">{{ "{$saleMaster->Symbol} ".number_format($saleMaster->payment_change,2,".",",") }}</td>
            </tr>
        </table>
        <hr>
        <table class="total-invoice">
            <tr>
                <td>Detalle de impuestos</td>
            </tr>
        </table>
        <hr>
        <table class="total-taxes">
            <tr>
                <td>Tipo</td>
                <td>Base</td>
                <td>Vr. Impuesto</td>
                <td>Total</td>
            </tr>
            @foreach ($taxes as $key => $tax)
            <tr class="detail">
                <td>{{ "{$tax->rate_abbre}-{$tax->name_taxe}-".number_format($tax->rate_value,0,".",",")."%" }}</td>
                <td>{{ "{$saleMaster->Symbol} ".number_format($tax->base, 2,".",",") }}</td>
                <td>{{ "{$saleMaster->Symbol} ".number_format($tax->tax_value, 2,".",",") }}</td>
                <td>{{ "{$saleMaster->Symbol} ".number_format($tax->total, 2,".",",") }}</td>
            </tr>
            @endforeach
        </table>
        <hr>
        <table id="footer">
            <tr>
                <td>
                    {!! $saleMaster->footline1 !!}
                </td>
            </tr>
            <tr>
                <td>
                    {!! $saleMaster->footline2 !!}
                </td>
            </tr>
            <tr>
                <td>
                    {!! $saleMaster->footline3 !!}
                </td>
            </tr>
            <tr>
                <td>
                    {!! $saleMaster->footline4 !!}
                </td>
            </tr>
        </table>
	</body>
</html>
