<cac:{{$node}}>
    <cbc:LineExtensionAmount currencyID="{{$currency->CurrencyISO}}">{{number_format($legalMonetaryTotals->line_extension_amount, 2, '.', '')}}</cbc:LineExtensionAmount>
    <cbc:TaxExclusiveAmount currencyID="{{$currency->CurrencyISO}}">{{number_format($legalMonetaryTotals->tax_exclusive_amount, 2, '.', '')}}</cbc:TaxExclusiveAmount>
    <cbc:TaxInclusiveAmount currencyID="{{$currency->CurrencyISO}}">{{number_format($legalMonetaryTotals->tax_inclusive_amount, 2, '.', '')}}</cbc:TaxInclusiveAmount>
    @if ($legalMonetaryTotals->allowance_total_amount)
        <cbc:AllowanceTotalAmount currencyID="{{$currency->CurrencyISO}}">{{number_format($legalMonetaryTotals->allowance_total_amount, 2, '.', '')}}</cbc:AllowanceTotalAmount>
    @endif
    @if ($legalMonetaryTotals->charge_total_amount)
        <cbc:ChargeTotalAmount currencyID="{{$currency->CurrencyISO}}">{{number_format($legalMonetaryTotals->charge_total_amount, 2, '.', '')}}</cbc:ChargeTotalAmount>
    @endif
    @if ($legalMonetaryTotals->pre_paid_amount)
        <cbc:PrePaidAmount currencyID="{{$currency->CurrencyISO}}">{{number_format($legalMonetaryTotals->pre_paid_amount, 2, '.', '')}}</cbc:PrePaidAmount>
    @endif
    <cbc:PayableAmount currencyID="{{$currency->CurrencyISO}}">{{number_format($legalMonetaryTotals->payable_amount, 2, '.', '')}}</cbc:PayableAmount>
</cac:{{$node}}>
