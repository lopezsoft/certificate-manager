@if ($paymentExchangeRate)
    <cac:PaymentExchangeRate>
        <cbc:SourceCurrencyCode>{{ $paymentExchangeRate->source_currency_code }}</cbc:SourceCurrencyCode>
        <cbc:SourceCurrencyBaseRate>{{ $paymentExchangeRate->source_currency_base_rate }}</cbc:SourceCurrencyBaseRate>
        <cbc:TargetCurrencyCode>{{ $paymentExchangeRate->target_currency_code }}</cbc:TargetCurrencyCode>
        <cbc:TargetCurrencyBaseRate>1.00</cbc:TargetCurrencyBaseRate>
        <cbc:CalculationRate>{{ $paymentExchangeRate->calculation_rate ?? 1 }}</cbc:CalculationRate>
        <cbc:Date>{{ $paymentExchangeRate->date ?? Carbon\Carbon::now()->format('Y-m-d') }}</cbc:Date>
    </cac:PaymentExchangeRate>
@endif
