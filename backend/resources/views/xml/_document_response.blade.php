<cac:DocumentResponse>
    <cac:Response>
        <cbc:ResponseCode>{{$typeEvent->code}}</cbc:ResponseCode>
        <cbc:Description>{{$typeEvent->name}}</cbc:Description>
    </cac:Response>
    <cac:DocumentReference>
        <cbc:ID>{{$documentReception->prefix}}{{$documentReception->folio}}</cbc:ID>
        <cbc:UUID schemeName="{{$responseTypeDocument->cufe_algorithm}}">{{$documentReception->cufe_cude}}</cbc:UUID>
        <cbc:DocumentTypeCode>{{$responseTypeDocument->code}}</cbc:DocumentTypeCode>
    </cac:DocumentReference>
    @if(isset($receptionPerson))
    <cac:IssuerParty>
        <cac:Person>
            <cbc:ID
                @if($receptionPerson->identityDocument->code == 31)
                    schemeID="{{$receptionPerson->dv}}"
                @endif
                schemeName="{{$receptionPerson->identityDocument->code}}">{{$receptionPerson->dni}}</cbc:ID>
            <cbc:FirstName>{{$receptionPerson->first_name}}</cbc:FirstName>
            <cbc:FamilyName>{{$receptionPerson->last_name}}</cbc:FamilyName>
            <cbc:JobTitle>{{$receptionPerson->job_title}}</cbc:JobTitle>
            <cbc:OrganizationDepartment>{{$receptionPerson->department}}</cbc:OrganizationDepartment>
        </cac:Person>
    </cac:IssuerParty>
    @endif
</cac:DocumentResponse>
