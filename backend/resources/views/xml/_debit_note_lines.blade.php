@foreach ($documentLines as $key => $debitNoteLine)
    <cac:DebitNoteLine>
        <cbc:ID>{{$key + 1}}</cbc:ID>
        <cbc:DebitedQuantity unitCode="{{$debitNoteLine->quantity_units->code}}">{{number_format($debitNoteLine->invoiced_quantity, 6, '.', '')}}</cbc:DebitedQuantity>
        <cbc:LineExtensionAmount currencyID="{{$currency->CurrencyISO}}">{{number_format($debitNoteLine->line_extension_amount, 2, '.', '')}}</cbc:LineExtensionAmount>
        @if ($debitNoteLine->free_of_charge_indicator === 'true')
            <cac:PricingReference>
                <cac:AlternativeConditionPrice>
                    <cbc:PriceAmount currencyID="{{$currency->CurrencyISO}}">{{number_format($debitNoteLine->price_amount, 2, '.', '')}}</cbc:PriceAmount>
                    <cbc:PriceTypeCode>{{$debitNoteLine->reference_price->code}}</cbc:PriceTypeCode>
                </cac:AlternativeConditionPrice>
            </cac:PricingReference>
        @endif
        {{-- TaxTotals line --}}
        @include('xml._tax_totals', ['node' => 'TaxTotal', 'taxTotals' => $debitNoteLine->tax_totals])
        @include('xml._tax_totals', ['node' => 'WithholdingTaxTotal', 'taxTotals' => $debitNoteLine->tax_retentions])
        {{-- AllowanceCharges line  --}}
        @include('xml._allowance_charges', ['allowanceCharges' => $debitNoteLine->allowance_charges])
        <cac:Item>
            <cbc:Description>{{$debitNoteLine->description}}</cbc:Description>
            <cac:StandardItemIdentification>
                <cbc:ID schemeID="{{$debitNoteLine->type_item_identifications->code}}" schemeName="EAN13" schemeAgencyID="{{$debitNoteLine->type_item_identifications->code_agency}}">{{$debitNoteLine->code}}</cbc:ID>
            </cac:StandardItemIdentification>
        </cac:Item>
        <cac:Price>
            <cbc:PriceAmount currencyID="{{$currency->CurrencyISO}}">{{number_format(($debitNoteLine->free_of_charge_indicator === 'true') ? 0 : $debitNoteLine->price_amount, 2, '.', '')}}</cbc:PriceAmount>
            <cbc:BaseQuantity unitCode="{{$debitNoteLine->quantity_units->code}}">{{number_format($debitNoteLine->base_quantity, 6, '.', '')}}</cbc:BaseQuantity>
        </cac:Price>
    </cac:DebitNoteLine>
@endforeach
