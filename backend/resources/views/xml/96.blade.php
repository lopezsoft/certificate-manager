<?xml version="1.0" encoding="UTF-8" standalone="no"?>
<ApplicationResponse
    xmlns="urn:oasis:names:specification:ubl:schema:xsd:ApplicationResponse-2"
    xmlns:cac="urn:oasis:names:specification:ubl:schema:xsd:CommonAggregateComponents-2"
    xmlns:cbc="urn:oasis:names:specification:ubl:schema:xsd:CommonBasicComponents-2"
    xmlns:ds="http://www.w3.org/2000/09/xmldsig#"
    xmlns:ext="urn:oasis:names:specification:ubl:schema:xsd:CommonExtensionComponents-2"
    xmlns:sts="dian:gov:co:facturaelectronica:Structures-2-1"
    xmlns:xades="http://uri.etsi.org/01903/v1.3.2#"
    xmlns:xades141="http://uri.etsi.org/01903/v1.4.1#"
    xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance"
    xsi:schemaLocation="urn:oasis:names:specification:ubl:schema:xsd:ApplicationResponse-2
    http://docs.oasis-open.org/ubl/os-UBL-2.1/xsd/maindoc/UBL-ApplicationResponse-2.1.xsd">
    {{-- UBLExtensions --}}
    @include('xml._ubl_extensions_response')
    <cbc:UBLVersionID>UBL 2.1</cbc:UBLVersionID>
    <cbc:CustomizationID>1</cbc:CustomizationID>
    <cbc:ProfileID>DIAN 2.1: ApplicationResponse de la Factura Electrónica de Venta</cbc:ProfileID>
    <cbc:ProfileExecutionID>{{$software->environment->code}}</cbc:ProfileExecutionID>
    <cbc:ID>{{$documentNumber}}</cbc:ID>
    <cbc:UUID schemeID="{{$software->environment->code}}" schemeName="{{$typeDocument->cufe_algorithm}}"/>
    <cbc:IssueDate>{{$date ?? Carbon\Carbon::now()->format('Y-m-d')}}</cbc:IssueDate>
    <cbc:IssueTime>{{$time ?? Carbon\Carbon::now()->format('H:i:s')}}-05:00</cbc:IssueTime>
    <cbc:Note>{{ $notes ?? '' }}</cbc:Note>
    {{-- SenderParty --}}
    @include('xml._party_tax_scheme_response', ['node' => 'SenderParty'])
    {{-- ReceiverParty --}}
    @include('xml._party_tax_scheme_response', ['node' => 'ReceiverParty', 'company' => $customer])
    {{-- DocumentResponse --}}
    @include('xml._document_response')
</ApplicationResponse>
