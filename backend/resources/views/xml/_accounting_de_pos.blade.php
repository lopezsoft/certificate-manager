<cac:AccountingCustomerParty>
    <cbc:AdditionalAccountID>{{$user->company->type_organization->code}}</cbc:AdditionalAccountID>
    <cac:Party>
        <cac:PartyIdentification>
            <cbc:ID schemeAgencyID="195" schemeAgencyName="CO, DIAN (Dirección de Impuestos y Aduanas Nacionales)" schemeID="{{$user->company->dv}}" schemeName="{{$user->company->identity_document->code}}">{{$user->company->dni}}</cbc:ID>
        </cac:PartyIdentification>
        <cac:PartyName>
            <cbc:Name>{{$user->company->company_name}}</cbc:Name>
        </cac:PartyName>
        <cac:PartyTaxScheme>
            <cbc:RegistrationName>{{$user->company->company_name}}</cbc:RegistrationName>
            <cbc:CompanyID schemeAgencyID="195" schemeAgencyName="CO, DIAN (Dirección de Impuestos y Aduanas Nacionales)" schemeID="{{$user->company->dv}}" schemeName="{{$user->company->identity_document->code}}">{{$user->company->dni}}</cbc:CompanyID>
            <cbc:TaxLevelCode listName="{{$user->company->tax_regime->code}}">{{$user->company->tax_level->code}}</cbc:TaxLevelCode>
            @if($user->company->dni == "222222222222")
            <cac:TaxScheme>
                <cbc:ID>ZZ</cbc:ID>
                <cbc:Name>No aplica</cbc:Name>
            </cac:TaxScheme>
            @else
            <cac:TaxScheme>
                <cbc:ID>{{$user->company->taxes->code}}</cbc:ID>
                <cbc:Name>{{$user->company->taxes->name_taxe}}</cbc:Name>
            </cac:TaxScheme>
            @endif
        </cac:PartyTaxScheme>
    </cac:Party>
</cac:AccountingCustomerParty>
