<CodigoQR>
    @if($software->environment->code == 2)
    https://catalogo-vpfe-hab.dian.gov.co/document/searchqr?documentkey={{ $cune }}
    @else
    https://catalogo-vpfe.dian.gov.co/document/searchqr?documentkey={{ $cune }}
    @endif
</CodigoQR>
<InformacionGeneral
Version="{{  $typeDocument->code == '102' ? 'V1.0: Documento Soporte de Pago de Nómina Electrónica' : 'V1.0: Nota de Ajuste de Documento Soporte de Pago de Nómina Electrónica'}}" Ambiente="{{ $software->environment->code }}"
TipoXML="{{ $typeDocument->code }}" CUNE="{{ $cune }}" EncripCUNE="CUNE-SHA384"
FechaGen="{{ $generalInformation->generation_date }}" HoraGen="{{ $generalInformation->generation_time }}-05:00"/>
@if($notes)
<Notas>
    {{ $notes }}
</Notas>
@endif

