<table class="table-footer">
    <thead>
    <tr>
        <th class="text-left">Forma de pago</th>
        <th class="text-left">Medio de pago</th>
        <th class="text-left">Valor del pago</th>
        {{--<th class="text-left">Plazo</th>--}}
        <th class="text-left"  style="width: 150px">Fecha de vencimiento</th>
    </tr>
    </thead>
    <tbody>
    @foreach ($saleMaster->payment as $key => $payment)
        <tr>
            <td>{{ $payment->paymentMethod->payment_method }}</td>
            <td>{{ $payment->meansPayment->payment_method }}</td>
            <td class="text-right">{{ "{$sale->currency->Symbol} ".number_format($payment->value_paid, 2,".",",") }}</td>
           {{-- <td>{{ $payment->timeLimit->term_name }}</td>--}}
            <td class="text-right">{{ Date('d-m-Y', strtotime($payment->payment_due_date ?? $sale->expirationDate ?? $sale->invoiceDate)) }}</td>
        </tr>
    @endforeach
    </tbody>
</table>
