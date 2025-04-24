@foreach ($documentLines as $key => $invoiceLine)
    <cac:InvoiceLine>
        <cbc:ID>{{($key + 1)}}</cbc:ID>
        @if($invoiceLine->notes)
        <cbc:Note>{{ $invoiceLine->notes }}</cbc:Note>
        @endif
        <cbc:InvoicedQuantity unitCode="{{$invoiceLine->quantity_units->code}}">{{number_format($invoiceLine->invoiced_quantity, 6, '.', '')}}</cbc:InvoicedQuantity>
        <cbc:LineExtensionAmount currencyID="{{$currency->CurrencyISO}}">{{number_format($invoiceLine->line_extension_amount, 2, '.', '')}}</cbc:LineExtensionAmount>
        @if(isset($invoiceLine->invoicePeriod))
            <cac:InvoicePeriod>
                <cbc:StartDate>{{$invoiceLine->invoicePeriod->StartDate}}</cbc:StartDate>
                <cbc:DescriptionCode>{{$invoiceLine->invoicePeriod->DescriptionCode}}</cbc:DescriptionCode>
                <cbc:Description>{{$invoiceLine->invoicePeriod->Description}}</cbc:Description>
            </cac:InvoicePeriod>
        @endif
        @if($typeDocument->code !== '05')
        <cbc:FreeOfChargeIndicator>{{$invoiceLine->free_of_charge_indicator}}</cbc:FreeOfChargeIndicator>
        @if ($invoiceLine->free_of_charge_indicator === 'true')
            <cac:PricingReference>
                <cac:AlternativeConditionPrice>
                    <cbc:PriceAmount currencyID="{{$currency->CurrencyISO}}">{{number_format($invoiceLine->price_amount, 2, '.', '')}}</cbc:PriceAmount>
                    <cbc:PriceTypeCode>{{$invoiceLine->reference_price->code}}</cbc:PriceTypeCode>
                </cac:AlternativeConditionPrice>
            </cac:PricingReference>
        @endif
        @endif
        {{-- AllowanceCharges line  --}}
        @include('xml._allowance_charges', ['allowanceCharges' => $invoiceLine->allowance_charges])
        {{-- TaxTotals line --}}
        @include('xml._tax_totals', ['node' => 'TaxTotal', 'taxTotals' => $invoiceLine->tax_totals])
        @include('xml._tax_totals', ['node' => 'WithholdingTaxTotal', 'taxTotals' => $invoiceLine->tax_retentions])
        <cac:Item>
            <cbc:Description>{{$invoiceLine->description}}</cbc:Description>
            @if(isset($invoiceLine->pack_size_numeric))
            <cbc:PackSizeNumeric>{{$invoiceLine->pack_size_numeric}}</cbc:PackSizeNumeric>
            @endif
            @if(isset($invoiceLine->brand_name))
            <cbc:BrandName>{{$invoiceLine->brand_name}}</cbc:BrandName>
            @endif
            @if(isset($invoiceLine->model_name))
            <cbc:ModelName>{{$invoiceLine->model_name}}</cbc:ModelName>
            @endif
            @if(isset($invoiceLine->sellers_item_identification))
            <cac:SellersItemIdentification>
                <cbc:ID>{{$invoiceLine->sellers_item_identification->id}}</cbc:ID>
                <cbc:ExtendedID>{{$invoiceLine->sellers_item_identification->extended_id}}</cbc:ExtendedID>
            </cac:SellersItemIdentification>
            @endif
            <cac:StandardItemIdentification>
                <cbc:ID schemeID="{{$invoiceLine->type_item_identifications->code}}" schemeName="EAN13" schemeAgencyID="{{$invoiceLine->type_item_identifications->code_agency}}">{{$invoiceLine->code}}</cbc:ID>
            </cac:StandardItemIdentification>
            @if($invoiceLine->mandate)
            <cac:InformationContentProviderParty>
                <cac:PowerOfAttorney>
                    <cac:AgentParty>
                        <cac:PartyIdentification>
                            <cbc:ID schemeAgencyID="195" schemeID="{{$invoiceLine->mandate->dv}}" schemeName="31">{{$invoiceLine->mandate->dni}}</cbc:ID>
                        </cac:PartyIdentification>
                    </cac:AgentParty>
                </cac:PowerOfAttorney>
            </cac:InformationContentProviderParty>
            @endif
        </cac:Item>
        <cac:Price>
            <cbc:PriceAmount currencyID="{{$currency->CurrencyISO}}">{{number_format(($invoiceLine->free_of_charge_indicator === 'true') ? 0 : $invoiceLine->price_amount, 2, '.', '')}}</cbc:PriceAmount>
            <cbc:BaseQuantity unitCode="{{$invoiceLine->quantity_units->code}}">{{number_format($invoiceLine->base_quantity, 6, '.', '')}}</cbc:BaseQuantity>
        </cac:Price>
    </cac:InvoiceLine>
@endforeach
