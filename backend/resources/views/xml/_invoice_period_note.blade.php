@if($invoicePeriod)
    <cac:InvoicePeriod>
        <cbc:StartDate>{{date('Y-m-d',strtotime($invoicePeriod->start_date))}}</cbc:StartDate>
        <cbc:StartTime>{{$invoicePeriod->start_time}}</cbc:StartTime>
        <cbc:EndDate>{{date('Y-m-d',strtotime($invoicePeriod->end_date))}}</cbc:EndDate>
        <cbc:EndTime>{{$invoicePeriod->end_time}}</cbc:EndTime>
    </cac:InvoicePeriod>
@endif
