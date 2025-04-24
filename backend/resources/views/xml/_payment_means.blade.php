 @foreach($paymentForm as $payment)
<cac:PaymentMeans>
    <cbc:ID>{{$payment->paymentMethod->code}}</cbc:ID>
    <cbc:PaymentMeansCode>{{$payment->meansPayment->code}}</cbc:PaymentMeansCode>
    @if ($payment->paymentMethod->code == '2')
        <cbc:PaymentDueDate>{{date('Y-m-d', strtotime($payment->payment_due_date))}}</cbc:PaymentDueDate>
    @endif
    <cbc:PaymentID>{{$payment->id}}</cbc:PaymentID>
</cac:PaymentMeans>
@endforeach
@if(isset($prepaidPayments))
<cac:PrepaidPayment>
    <cbc:ID>{{$prepaidPayments->id}}</cbc:ID>
    <cbc:PaidAmount  currencyID="{{$currency->CurrencyISO}}">{{number_format($prepaidPayments->paid_amount,2, '.', ',')}}</cbc:PaidAmount>
    <cbc:ReceivedDate>{{$prepaidPayments->received_date}}</cbc:ReceivedDate>
    <cbc:PaidDate>{{$prepaidPayments->paid_date}}</cbc:PaidDate>
    <cbc:InstructionID>{{$prepaidPayments->instruction_id}}</cbc:InstructionID>
</cac:PrepaidPayment>
@endif
