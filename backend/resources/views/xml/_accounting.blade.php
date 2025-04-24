<cac:{{$node}}>
    <cbc:AdditionalAccountID>{{$user->company->type_organization->code}}</cbc:AdditionalAccountID>
    <cac:Party>
        @if(!isSupportDocument($typeDocument->code))
        <cac:PartyIdentification>
           <cbc:ID schemeAgencyID="195" schemeAgencyName="CO, DIAN (Dirección de Impuestos y Aduanas Nacionales)" schemeID="{{$user->company->dv}}" schemeName="{{$user->company->identity_document->code}}">{{$user->company->dni}}</cbc:ID>
        </cac:PartyIdentification>
        <cac:PartyName>
            <cbc:Name>{{!empty($user->company->trade_name) ? $user->company->trade_name : $user->company->company_name}}</cbc:Name>
        </cac:PartyName>
        @endif
        @if(isset($supplier) && (!isFinalConsumer($user->company->dni)) && (isset($user->company->city) || isset($user->company->address)))
            <cac:PhysicalLocation>
                <cac:Address>
                    @if(isset($user->company->city))
                    <cbc:ID>{{$user->company->city->city_code}}</cbc:ID>
                    <cbc:CityName>{{$user->company->city->name_city}}</cbc:CityName>
                    @if(isset($user->company->postal_code) && strlen($user->company->postal_code) > 3)
                        <cbc:PostalZone>{{$user->company->postal_code ?? "000000"}}</cbc:PostalZone>
                    @endif
                    <cbc:CountrySubentity>{{$user->company->city->department->name_department}}</cbc:CountrySubentity>
                    <cbc:CountrySubentityCode>{{$user->company->city->department->code}}</cbc:CountrySubentityCode>
                    @elseif(isset($user->company->city_name))
                    <cbc:CityName>{{$user->company->city_name}}</cbc:CityName>
                    @if(isset($user->company->postal_code))
                        <cbc:PostalZone>{{$user->company->postal_code}}</cbc:PostalZone>
                    @endif
                    @endif
                    @if(isset($user->company->address))
                    <cac:AddressLine>
                        <cbc:Line>{{$user->company->address}}</cbc:Line>
                    </cac:AddressLine>
                    @endif
                    @if(isset($user->company->city) || isset($user->company->address))
                    <cac:Country>
                        <cbc:IdentificationCode>{{$user->company->country->abbreviation_A2}}</cbc:IdentificationCode>
                        <cbc:Name languageID="{{strtolower($language->ISO_639_1)}}">{{$user->company->country->country_name}}</cbc:Name>
                    </cac:Country>
                    @endif
                </cac:Address>
            </cac:PhysicalLocation>
        @endif
        <cac:PartyTaxScheme>
            <cbc:RegistrationName>{{$user->company->company_name}}</cbc:RegistrationName>
            <cbc:CompanyID schemeAgencyID="195" schemeAgencyName="CO, DIAN (Dirección de Impuestos y Aduanas Nacionales)" schemeID="{{$user->company->dv}}" schemeName="{{$user->company->identity_document->code}}">{{$user->company->dni}}</cbc:CompanyID>
            <cbc:TaxLevelCode listName="{{$user->company->tax_regime->code}}">{{$user->company->tax_level->code}}</cbc:TaxLevelCode>
            @if(isset($user->company->city) && $typeDocument->code !== '05' && $typeDocument->code !== '95' && (!isFinalConsumer($user->company->dni)))
            <cac:RegistrationAddress>
                <cbc:ID>{{$user->company->city->city_code}}</cbc:ID>
                <cbc:CityName>{{$user->company->city->name_city}}</cbc:CityName>
                <cbc:CountrySubentity>{{$user->company->city->department->name_department}}</cbc:CountrySubentity>
                <cbc:CountrySubentityCode>{{$user->company->city->department->code}}</cbc:CountrySubentityCode>
                <cac:AddressLine>
                    <cbc:Line>{{$user->company->address}}</cbc:Line>
                </cac:AddressLine>
                <cac:Country>
                    <cbc:IdentificationCode>{{$user->company->country->abbreviation_A2}}</cbc:IdentificationCode>
                    <cbc:Name languageID="{{strtolower($language->ISO_639_1)}}">{{$user->company->country->country_name}}</cbc:Name>
                </cac:Country>
            </cac:RegistrationAddress>
            @endif
            <cac:TaxScheme>
                <cbc:ID>{{$user->company->taxes->code}}</cbc:ID>
                <cbc:Name>{{$user->company->taxes->name_taxe}}</cbc:Name>
            </cac:TaxScheme>
        </cac:PartyTaxScheme>
        @if(!isSupportDocument($typeDocument->code) && (!isFinalConsumer($user->company->dni)))
        <cac:PartyLegalEntity>
            <cbc:RegistrationName>{{$user->company->company_name}}</cbc:RegistrationName>
            <cbc:CompanyID schemeAgencyID="195" schemeAgencyName="CO, DIAN (Dirección de Impuestos y Aduanas Nacionales)" schemeID="{{$user->company->dv}}" schemeName="{{$user->company->identity_document->code}}">{{$user->company->dni}}</cbc:CompanyID>
            <cac:CorporateRegistrationScheme>
                @isset($supplier)
                    <cbc:ID>{{$resolution->prefix}}</cbc:ID>
                @endisset
                <cbc:Name>{{$user->company->merchant_registration}}</cbc:Name>
            </cac:CorporateRegistrationScheme>
        </cac:PartyLegalEntity>
        @if(isset($user->company->mobile) || isset($user->company->email))
        <cac:Contact>
            @if(isset($user->company->mobile))
            <cbc:Telephone>{{$user->company->mobile}}</cbc:Telephone>
            @endif
            @if(isset($user->company->email))
            <cbc:ElectronicMail>{{$user->company->email}}</cbc:ElectronicMail>
            @endif
        </cac:Contact>
        @endif
        @endif
    </cac:Party>
</cac:{{$node}}>
