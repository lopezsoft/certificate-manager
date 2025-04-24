@if ($additionalDocumentReferences)
	@foreach ($additionalDocumentReferences as $key => $additionalDocumentReference)
	<cac:AdditionalDocumentReference>
		<cbc:ID>{{$additionalDocumentReference->reference_number ?? null}}</cbc:ID>
		<cbc:IssueDate>{{date('Y-m-d',strtotime($additionalDocumentReference->reference_date ?? Carbon\Carbon::now()->format('Y-m-d')))}}</cbc:IssueDate>
		<cbc:DocumentTypeCode>{{$additionalDocumentReference->code ?? null }}</cbc:DocumentTypeCode>
		<cbc:DocumentType>{{$additionalDocumentReference->name_reference ?? null }}</cbc:DocumentType>
	</cac:AdditionalDocumentReference>
	@endforeach
@endif
