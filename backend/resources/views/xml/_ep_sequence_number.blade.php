@if($sequenceNumber)
    <NumeroSecuenciaXML
        @if($sequenceNumber->worker_code)
        CodigoTrabajador="{{ $sequenceNumber->worker_code }}"
        @endif
        Prefijo="{{ $sequenceNumber->prefix }}" Consecutivo="{{ $sequenceNumber->consecutive }}" Numero="{{ $sequenceNumber->number }}" />
@endif
