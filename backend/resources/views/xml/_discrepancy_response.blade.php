@if($discrepancyResponse)
<cac:DiscrepancyResponse>
    <cbc:ReferenceID>{{$discrepancyResponse->reference_id}}</cbc:ReferenceID>
    <cbc:ResponseCode>{{$discrepancyResponse->code}}</cbc:ResponseCode>
    <cbc:Description>{{$discrepancyResponse->description}}</cbc:Description>
</cac:DiscrepancyResponse>
@endif
