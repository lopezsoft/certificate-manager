@if($orderReference)
	<cac:OrderReference>
		<cbc:ID>{{$orderReference->reference_number}}</cbc:ID>
		<cbc:IssueDate>{{date('Y-m-d',strtotime($orderReference->reference_date ?? Carbon\Carbon::now()->format('Y-m-d')))}}</cbc:IssueDate>
	</cac:OrderReference>
@endif