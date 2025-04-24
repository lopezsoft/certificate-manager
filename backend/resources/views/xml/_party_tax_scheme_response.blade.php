<cac:{{$node}}>
    <cac:PartyTaxScheme>
        <cbc:RegistrationName>{{$company->company_name}}</cbc:RegistrationName>
        <cbc:CompanyID schemeAgencyID="195" schemeAgencyName="CO, DIAN (Dirección de Impuestos y Aduanas Nacionales)"
                       schemeID="{{$company->dv}}" schemeName="{{$company->identity_document->code}}"
                       schemeVersionID="{{$company->type_organization->code}}">{{$company->dni}}</cbc:CompanyID>
        <cbc:TaxLevelCode listName="{{$company->tax_regime->code}}">{{$company->tax_level->code}}</cbc:TaxLevelCode>
        <cac:TaxScheme>
            <cbc:ID>{{$company->taxes->code}}</cbc:ID>
            <cbc:Name>{{$company->taxes->name_taxe}}</cbc:Name>
        </cac:TaxScheme>
    </cac:PartyTaxScheme>
</cac:{{$node}}>
