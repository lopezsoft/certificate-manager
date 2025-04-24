<sts:InvoiceControl>
    <sts:InvoiceAuthorization>{{$resolution->resolution_number}}</sts:InvoiceAuthorization>
    <sts:AuthorizationPeriod>
        <cbc:StartDate>{{$resolution->date_from}}</cbc:StartDate>
        <cbc:EndDate>{{$resolution->date_up}}</cbc:EndDate>
    </sts:AuthorizationPeriod>
    <sts:AuthorizedInvoices>
        @if ($resolution->prefix)
            <sts:Prefix>{{$resolution->prefix}}</sts:Prefix>
        @endif
        <sts:From>{{$resolution->range_from}}</sts:From>
        <sts:To>{{$resolution->range_up}}</sts:To>
    </sts:AuthorizedInvoices>
</sts:InvoiceControl>
