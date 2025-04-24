@foreach ($documentLines as $key => $creditNoteLine)
    <cac:CreditNoteLine>
        <cbc:ID>{{$key + 1}}</cbc:ID>
        <cbc:CreditedQuantity unitCode="{{$creditNoteLine->quantity_units->code}}">{{number_format($creditNoteLine->invoiced_quantity, 6, '.', '')}}</cbc:CreditedQuantity>
        <cbc:LineExtensionAmount currencyID="{{$currency->CurrencyISO}}">{{number_format($creditNoteLine->line_extension_amount, 2, '.', '')}}</cbc:LineExtensionAmount>
        <cbc:FreeOfChargeIndicator>{{$creditNoteLine->free_of_charge_indicator}}</cbc:FreeOfChargeIndicator>
        @if ($creditNoteLine->free_of_charge_indicator === 'true')
            <cac:PricingReference>
                <cac:AlternativeConditionPrice>
                    <cbc:PriceAmount currencyID="{{$currency->CurrencyISO}}">{{number_format($creditNoteLine->price_amount, 2, '.', '')}}</cbc:PriceAmount>
                    <cbc:PriceTypeCode>{{$creditNoteLine->reference_price->code}}</cbc:PriceTypeCode>
                </cac:AlternativeConditionPrice>
            </cac:PricingReference>
        @endif
        {{-- TaxTotals line --}}
        @include('xml._tax_totals', ['node' => 'TaxTotal', 'taxTotals' => $creditNoteLine->tax_totals])
        {{-- AllowanceCharges line  --}}
        @include('xml._allowance_charges', ['allowanceCharges' => $creditNoteLine->allowance_charges])
        <cac:Item>
            <cbc:Description>{{$creditNoteLine->description}}</cbc:Description>
            <cac:StandardItemIdentification>
                <cbc:ID schemeID="{{$creditNoteLine->type_item_identifications->code}}"
                        schemeName="{{$creditNoteLine->type_item_identifications->name}}"
                        schemeAgencyID="{{$creditNoteLine->type_item_identifications->code_agency}}">{{$creditNoteLine->code}}</cbc:ID>
            </cac:StandardItemIdentification>
        </cac:Item>
        <cac:Price>
            <cbc:PriceAmount currencyID="{{$currency->CurrencyISO}}">{{number_format(($creditNoteLine->free_of_charge_indicator === 'true') ? 0 : $creditNoteLine->price_amount, 2, '.', '')}}</cbc:PriceAmount>
            <cbc:BaseQuantity unitCode="{{$creditNoteLine->quantity_units->code}}">{{number_format($creditNoteLine->base_quantity, 6, '.', '')}}</cbc:BaseQuantity>
        </cac:Price>
    </cac:CreditNoteLine>
@endforeach
