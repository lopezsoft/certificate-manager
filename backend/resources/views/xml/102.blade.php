<?xml version="1.0" encoding="UTF-8"?>
<!--Version #1.0-->
<NominaIndividual
    xmlns="dian:gov:co:facturaelectronica:NominaIndividual"
    xmlns:xs="http://www.w3.org/2001/XMLSchema-instance"
    xmlns:ds="http://www.w3.org/2000/09/xmldsig#"
    xmlns:ext="urn:oasis:names:specification:ubl:schema:xsd:CommonExtensionComponents-2"
    xmlns:xades="http://uri.etsi.org/01903/v1.3.2#"
    xmlns:xades141="http://uri.etsi.org/01903/v1.4.1#"
    xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance"
    SchemaLocation=""
    xsi:schemaLocation="dian:gov:co:facturaelectronica:NominaIndividual NominaIndividualElectronicaXSD.xsd">
    {{-- UBLExtensions --}}
    @include('xml._ubl_extensions_payroll')
    {{-- Period --}}
    @include('xml._ep_period')
    {{-- Número de secuencia del XML  --}}
    @include('xml._ep_sequence_number')
    {{-- Lugar de generación del XML  --}}
    @include('xml._ep_generation_place')
    {{-- Proveedor  --}}
    @include('xml._ep_provider')
    {{-- Informacion general  --}}
    @include('xml._ep_general_information')
    {{-- Informacion empleador  --}}
    @include('xml._ep_employer')
    {{-- Informacion trabajador  --}}
    @include('xml._ep_employee')
    {{-- Informacion forma de pago --}}
    @include('xml._ep_payment')
    {{-- Informacion devengados --}}
    @include('xml._ep_earn')
    {{-- Informacion deducciones --}}
    @include('xml._ep_deductions')
    @if($rounding)
    <Redondeo>{{ number_format($rounding, 2, '.', '') }}</Redondeo>
    @endif
	<DevengadosTotal>{{ number_format($total_earned, 2, '.', '') }}</DevengadosTotal>
	<DeduccionesTotal>{{ number_format($deductions_total, 2, '.', '') }}</DeduccionesTotal>
	<ComprobanteTotal>{{ number_format($total_voucher, 2, '.', '') }}</ComprobanteTotal>
</NominaIndividual>
