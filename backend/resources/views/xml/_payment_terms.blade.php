<cac:PaymentTerms>
    <cbc:ReferenceEventCode>{{$paymentForm->code}}</cbc:ReferenceEventCode>
    @if ($paymentForm->code == '2')
        <cac:SettlementPeriod>
            <cbc:DurationMeasure unitCode="{{$paymentForm->duration_measure_unit_code}}">{{$paymentForm->payment_term}}</cbc:DurationMeasure>
        </cac:SettlementPeriod>
    @endif
</cac:PaymentTerms>
