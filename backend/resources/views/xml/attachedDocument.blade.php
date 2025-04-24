<?xml version="1.0" encoding="UTF-8"?>
<AttachedDocument
    xmlns="urn:oasis:names:specification:ubl:schema:xsd:AttachedDocument-2"
    xmlns:ds="http://www.w3.org/2000/09/xmldsig#"
    xmlns:cac="urn:oasis:names:specification:ubl:schema:xsd:CommonAggregateComponents-2"
    xmlns:cbc="urn:oasis:names:specification:ubl:schema:xsd:CommonBasicComponents-2"
    xmlns:ccts="urn:un:unece:uncefact:data:specification:CoreComponentTypeSchemaModule:2"
    xmlns:ext="urn:oasis:names:specification:ubl:schema:xsd:CommonExtensionComponents-2"
    xmlns:xades="http://uri.etsi.org/01903/v1.3.2#"
    xmlns:xades141="http://uri.etsi.org/01903/v1.4.1#">
    {{-- UBLExtensions --}}
    @include('xml._ubl_extensions_attached')
    <cbc:UBLVersionID>UBL 2.1</cbc:UBLVersionID>
    <cbc:CustomizationID>Documentos adjuntos</cbc:CustomizationID>
    <cbc:ProfileID>Factura Electrónica de Venta</cbc:ProfileID>
    <cbc:ProfileExecutionID>{{$company->software->environment->code}}</cbc:ProfileExecutionID>
    <cbc:ID>{{$document_number}}</cbc:ID>
    <cbc:IssueDate>{{$date ?? Carbon\Carbon::now()->format('Y-m-d')}}</cbc:IssueDate>
    <cbc:IssueTime>{{$time ?? Carbon\Carbon::now()->format('H:i:s')}}-05:00</cbc:IssueTime>
    <cbc:DocumentType>Contenedor de Factura Electrónica</cbc:DocumentType>
    <cbc:ParentDocumentID>{{$document_number}}</cbc:ParentDocumentID>
     {{-- SenderParty --}}
     @include('xml._party_tax_scheme', ['node' => 'SenderParty'])
     {{-- ReceiverParty --}}
     @include('xml._party_tax_scheme', ['node' => 'ReceiverParty', 'user' => $customer])
    <cac:Attachment>
        <cac:ExternalReference>
            <cbc:MimeCode>text/xml</cbc:MimeCode>
            <cbc:EncodingCode>UTF-8</cbc:EncodingCode>
            <cbc:Description>
                <![CDATA[{{$xml}}]]>
            </cbc:Description>
        </cac:ExternalReference>
    </cac:Attachment>
    <cac:ParentDocumentLineReference>
        <cbc:LineID>1</cbc:LineID>
        <cac:DocumentReference>
            <cbc:ID>{{$document_number}}</cbc:ID>
            <cbc:UUID schemeName="CUFE-SHA384">{{$cufe}}</cbc:UUID>
            <cbc:IssueDate>{{$response_date ?? Carbon\Carbon::now()->format('Y-m-d')}}</cbc:IssueDate>
            <cbc:DocumentType>ApplicationResponse</cbc:DocumentType>
            <cac:Attachment>
                <cac:ExternalReference>
                    <cbc:MimeCode>text/xml</cbc:MimeCode>
                    <cbc:EncodingCode>UTF-8</cbc:EncodingCode>
                    <cbc:Description>
                        <![CDATA[{{$app_response}}]]>
                    </cbc:Description>
                </cac:ExternalReference>
            </cac:Attachment>
            <cac:ResultOfVerification>
                <cbc:ValidatorID>Unidad Especial Dirección de Impuestos y Aduanas Nacionales</cbc:ValidatorID>
                <cbc:ValidationResultCode>{{$validation_code ?? '02'}}</cbc:ValidationResultCode>
                <cbc:ValidationDate>{{$validation_date ?? Carbon\Carbon::now()->format('Y-m-d')}}</cbc:ValidationDate>
                <cbc:ValidationTime>{{$validation_time ?? Carbon\Carbon::now()->format('H:i:s')}}</cbc:ValidationTime>
            </cac:ResultOfVerification>
        </cac:DocumentReference>
    </cac:ParentDocumentLineReference>
</AttachedDocument>
