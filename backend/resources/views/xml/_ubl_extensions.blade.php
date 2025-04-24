<ext:UBLExtensions>
    @if($hasHealth)
        @include('xml._ubl_extensions_health')
    @endif
    <ext:UBLExtension>
        <ext:ExtensionContent>
            <sts:DianExtensions>
                @if($resolution->type_document_id > 0)
                    @includeWhen($resolution, 'xml._invoice_control')
                @endif
                <sts:InvoiceSource>
                    <cbc:IdentificationCode listAgencyID="6" listAgencyName="United Nations Economic Commission for Europe" listSchemeURI="urn:oasis:names:specification:ubl:codelist:gc:CountryIdentificationCode-2.1">{{$company->country->abbreviation_A2}}</cbc:IdentificationCode>
                </sts:InvoiceSource>
                <sts:SoftwareProvider>
                    <sts:ProviderID schemeAgencyID="195" schemeAgencyName="CO, DIAN (Dirección de Impuestos y Aduanas Nacionales)" @if ($company->identity_document->code == '31') schemeID="{{$company->dv}}" @endif schemeName="{{$company->identity_document->code}}">{{$company->dni}}</sts:ProviderID>
                    <sts:SoftwareID schemeAgencyID="195" schemeAgencyName="CO, DIAN (Dirección de Impuestos y Aduanas Nacionales)">{{$software->identification}}</sts:SoftwareID>
                </sts:SoftwareProvider>
                <sts:SoftwareSecurityCode schemeAgencyID="195" schemeAgencyName="CO, DIAN (Dirección de Impuestos y Aduanas Nacionales)"/>
                <sts:AuthorizationProvider>
                    <sts:AuthorizationProviderID schemeAgencyID="195" schemeAgencyName="CO, DIAN (Dirección de Impuestos y Aduanas Nacionales)" schemeID="4" schemeName="31">800197268</sts:AuthorizationProviderID>
                </sts:AuthorizationProvider>
                <sts:QRCode>QRCode</sts:QRCode>
            </sts:DianExtensions>
        </ext:ExtensionContent>
    </ext:UBLExtension>
    @if(in_array($software->type_id, [5,6]))
        @include('xml._ubl_extensions_de_pos')
    @endif
    <ext:UBLExtension>
        <ext:ExtensionContent/>
    </ext:UBLExtension>
    @if(in_array($software->type_id, [7]))
        @include('xml._ubl_extensions_de_spd')
    @endif
    @if(in_array($software->type_id, [4]))
        @include('xml._ubl_extensions_de_pos')
    @endif
</ext:UBLExtensions>
