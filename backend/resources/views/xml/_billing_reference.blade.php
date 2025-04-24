@if($billingReference)
<cac:BillingReference>
    <cac:InvoiceDocumentReference>
        <cbc:ID>{{$billingReference->number}}</cbc:ID>
        <cbc:UUID schemeName="{{$billingReference->scheme_name}}">{{$billingReference->uuid}}</cbc:UUID>
        <cbc:IssueDate>{{date('Y-m-d',strtotime($billingReference->date))}}</cbc:IssueDate>
        @isset($DocumentTypeCode)
        <cbc:DocumentTypeCode>{{$DocumentTypeCode}}</cbc:DocumentTypeCode>
        @endisset
    </cac:InvoiceDocumentReference>
</cac:BillingReference>
@endif
