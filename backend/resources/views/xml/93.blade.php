<?xml version="1.0" encoding="UTF-8" standalone="no"?>
<DebitNote
    xmlns="urn:oasis:names:specification:ubl:schema:xsd:DebitNote-2"
    xmlns:cac="urn:oasis:names:specification:ubl:schema:xsd:CommonAggregateComponents-2"
    xmlns:cbc="urn:oasis:names:specification:ubl:schema:xsd:CommonBasicComponents-2"
    xmlns:ds="http://www.w3.org/2000/09/xmldsig#"
    xmlns:ext="urn:oasis:names:specification:ubl:schema:xsd:CommonExtensionComponents-2"
    xmlns:sts="http://www.dian.gov.co/contratos/facturaelectronica/v1/Structures"
    xmlns:xades="http://uri.etsi.org/01903/v1.3.2#"
    xmlns:xades141="http://uri.etsi.org/01903/v1.4.1#"
    xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance"
    xsi:schemaLocation="urn:oasis:names:specification:ubl:schema:xsd:DebitNote-2    http://docs.oasis-open.org/ubl/os-UBL-2.1/xsd/maindoc/UBL-DebitNote-2.1.xsd">
    {{-- UBLExtensions --}}
    @include('xml._ubl_extensions')
    <cbc:UBLVersionID>UBL 2.1</cbc:UBLVersionID>
    <cbc:CustomizationID>{{$operationType->code}}</cbc:CustomizationID>
    <cbc:ProfileID>DIAN 2.1: Nota de ajuste débito al documento equivalente</cbc:ProfileID>
    <cbc:ProfileExecutionID>{{$software->environment->code}}</cbc:ProfileExecutionID>
    <cbc:ID>{{$document_number}}</cbc:ID>
    <cbc:UUID schemeID="{{$software->environment->code}}" schemeName="{{$typeDocument->cufe_algorithm}}"/>
    <cbc:IssueDate>{{$date ?? Carbon\Carbon::now()->format('Y-m-d')}}</cbc:IssueDate>
    <cbc:IssueTime>{{$time ?? Carbon\Carbon::now()->format('H:i:s')}}-05:00</cbc:IssueTime>
    <cbc:DocumentCurrencyCode>{{$currency->CurrencyISO}}</cbc:DocumentCurrencyCode>
    <cbc:LineCountNumeric>{{$documentLines->count()}}</cbc:LineCountNumeric>
    {{-- DiscrepancyResponse --}}
    @include('xml._discrepancy_response')
    {{-- BillingReference --}}
    @include('xml._billing_reference')
    {{-- AccountingSupplierParty --}}
    @include('xml._accounting', ['node' => 'AccountingSupplierParty', 'supplier' => true])
    {{-- AccountingCustomerParty --}}
    @include('xml._accounting_de_pos', ['user' => $customer])
    {{-- PaymentMeans --}}
    @include('xml._payment_means')
    {{-- AllowanceCharges --}}
    @include('xml._allowance_charges')
    {{-- PaymentExchangeRate --}}
    @include('xml._payment_exchange_rate')
    {{-- TaxTotals --}}
    @include('xml._tax_totals', ['node' => 'TaxTotal'])
    @include('xml._tax_totals', ['node' => 'WithholdingTaxTotal', 'taxTotals' => $taxRetentions])
    {{-- RequestedMonetaryTotal --}}
    @include('xml._legal_monetary_total', ['node' => 'RequestedMonetaryTotal' ,'legalMonetaryTotal' => $legalMonetaryTotals])
    {{-- DebitNoteLine --}}
    @include('xml._debit_note_lines')
</DebitNote>
