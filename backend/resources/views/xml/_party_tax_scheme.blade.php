<cac:{{$node}}>
    <cac:PartyTaxScheme>
        <cbc:RegistrationName>{{$user->company->company_name}}</cbc:RegistrationName>
        <cbc:CompanyID schemeAgencyID="195" schemeID="{{$user->company->dv}}" schemeName="31">{{$user->company->dni}}</cbc:CompanyID>
        <cbc:TaxLevelCode listName="{{$user->company->tax_regime->code}}">{{$user->company->tax_level->code}}</cbc:TaxLevelCode>
        <cac:TaxScheme>
            <cbc:ID>{{$user->company->taxes->code}}</cbc:ID>
            <cbc:Name>{{$user->company->taxes->name_taxe}}</cbc:Name>
        </cac:TaxScheme>
    </cac:PartyTaxScheme>
</cac:{{$node}}>
