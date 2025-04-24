<Pago Forma="{{ $paymentForm->code }}" Metodo="{{ $paymentForm->payment_method_code }}"
    Banco="{{ $paymentForm->bank }}" TipoCuenta="{{ $paymentForm->account_type }}"
    NumeroCuenta="{{ $paymentForm->account_number }}" />
<FechasPagos>
    <FechaPago>{{ $date ?? Carbon\Carbon::now()->format('Y-m-d') }}</FechaPago>
</FechasPagos>
