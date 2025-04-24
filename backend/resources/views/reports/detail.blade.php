<table class="table-detail">
    <thead>
    <tr>
        <th colspan="2">CÓDIGO</th>
        <th>DETALLE</th>
        <th>CANT</th>
        <th>U.M</th>
        <th class="price">PRECIO</th>
        @if ($discount > 0)
        <th class="discount">DESCUENTO</th>
        @endif
        @if ($chargeValue > 0)
        <th class="discount">RECARGO</th>
        @endif
        {{-- Cabeceras Dinámicas (Extra Data) --}}
        @foreach ($extraDataHeaders as $header)
            {{-- Puedes formatear el título si quieres (ej. capitalizar) --}}
            <th>{{ Str::upper(str_replace('_', ' ', $header)) }}</th>
        @endforeach
        <th>IVA</th>
        <th class="total">TOTAL</th>
    </tr>
    </thead>
    <tbody>
    @foreach ($details as $line)
        <tr>
            <td class="text-right count">
                {{ ++$count }}
            </td>
            <td class="text-right code">
                {{ $line->sku ?? $line->code }}
            </td>
            <td>
                {{ $line->detail }}
            </td>
            <td class="text-right amount">
                {{ $line->amount }}
            </td>
            <td class="text-center um">
                {{ $line->abbre_unit }}
            </td>
            <td class="price text-right">
                {{ $line->unit_price }}
            </td>
            @if($discount > 0)
            <td class="discount text-right">
                @if($line->discount > 0)
                {{ "{$saleMaster->currency->Symbol} {$line->discount}" }}
                @endif
            </td>
            @endif
            @if ($chargeValue > 0)
            <td class="discount text-right">
                @if($line->charge > 0)
                {{ "{$saleMaster->currency->Symbol} {$line->charge}" }}
                @endif
            </td>
            @endif
            {{-- Dentro del bucle foreach de las líneas y el bucle de las cabeceras extra --}}
            @foreach ($extraDataHeaders as $header)
                @php
                    $extraItemData = $line->processed_extra_data[$header] ?? null;
                    $value = $extraItemData['value'] ?? '';
                    $align = $extraItemData['align'] ?? 'left';
                @endphp
                <td style="text-align: {{ $align }};">
                    {{ $value }}
                </td>
            @endforeach
            <td class="vat text-right">
                @if ($line->vat > 0)
                    {{ $line->vat }}%
                @endif
            </td>
            <td class="total text-right">
                {{ $line->total }}
            </td>
        </tr>
    @endforeach
    </tbody>
</table>
