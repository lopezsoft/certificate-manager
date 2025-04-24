@if($taxTotals && $taxTotals->count() > 0 )
@foreach ($taxTotals as $key => $taxTotal)
    @if($taxTotal['tax_subtotal'])
        <cac:{{$node}}>
            <cbc:TaxAmount currencyID="{{$currency->CurrencyISO}}">{{number_format($taxTotal['tax_amount'], 2, '.', '')}}</cbc:TaxAmount>
            @foreach($taxTotal['tax_subtotal'] as $key => $tax_subtotal)
                <cac:TaxSubtotal>
                    @if (!$tax_subtotal->is_fixed_value)
                        <cbc:TaxableAmount currencyID="{{$currency->CurrencyISO}}">{{number_format($tax_subtotal->taxable_amount, 2, '.', '')}}</cbc:TaxableAmount>
                    @endif
                    <cbc:TaxAmount currencyID="{{$currency->CurrencyISO}}">{{number_format($tax_subtotal->tax_amount, 2, '.', '')}}</cbc:TaxAmount>
                    @if ($tax_subtotal->is_fixed_value)
                        <cbc:BaseUnitMeasure unitCode="{{$tax_subtotal->unit_measure->code}}">{{number_format($tax_subtotal->base_unit_measure, 6, '.', '')}}</cbc:BaseUnitMeasure>
                        <cbc:PerUnitAmount currencyID="{{$currency->CurrencyISO}}">{{number_format($tax_subtotal->per_unit_amount, 2, '.', '')}}</cbc:PerUnitAmount>
                    @endif
                    <cac:TaxCategory>
                        @if (!$tax_subtotal->is_fixed_value)
                            <cbc:Percent>{{number_format($tax_subtotal->percent, 2, '.', '')}}</cbc:Percent>
                        @endif
                        <cac:TaxScheme>
                            <cbc:ID>{{$tax_subtotal->tax->code}}</cbc:ID>
                            <cbc:Name>{{$tax_subtotal->tax->name_taxe}}</cbc:Name>
                        </cac:TaxScheme>
                    </cac:TaxCategory>
                </cac:TaxSubtotal>
            @endforeach
        </cac:{{$node}}>
    @else
        <cac:{{$node}}>
            <cbc:TaxAmount currencyID="{{$currency->CurrencyISO}}">{{number_format($taxTotal->tax_amount, 2, '.', '')}}</cbc:TaxAmount>
            <cac:TaxSubtotal>
                @if (!$taxTotal->is_fixed_value)
                    <cbc:TaxableAmount currencyID="{{$currency->CurrencyISO}}">{{number_format($taxTotal->taxable_amount, 2, '.', '')}}</cbc:TaxableAmount>
                @endif
                <cbc:TaxAmount currencyID="{{$currency->CurrencyISO}}">{{number_format($taxTotal->tax_amount, 2, '.', '')}}</cbc:TaxAmount>
                @if ($taxTotal->is_fixed_value)
                    <cbc:BaseUnitMeasure unitCode="{{$taxTotal->unit_measure->code}}">{{number_format($taxTotal->base_unit_measure, 6, '.', '')}}</cbc:BaseUnitMeasure>
                    <cbc:PerUnitAmount currencyID="{{$currency->CurrencyISO}}">{{number_format($taxTotal->per_unit_amount, 2, '.', '')}}</cbc:PerUnitAmount>
                @endif
                <cac:TaxCategory>
                    @if (!$taxTotal->is_fixed_value)
                        <cbc:Percent>{{number_format($taxTotal->percent, 2, '.', '')}}</cbc:Percent>
                    @endif
                    <cac:TaxScheme>
                        <cbc:ID>{{$taxTotal->tax->code}}</cbc:ID>
                        <cbc:Name>{{$taxTotal->tax->name_taxe}}</cbc:Name>
                    </cac:TaxScheme>
                </cac:TaxCategory>
            </cac:TaxSubtotal>
        </cac:{{$node}}>
    @endif
@endforeach
@endif
