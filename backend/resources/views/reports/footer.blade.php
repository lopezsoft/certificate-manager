<table class="table-footer">
    @if($resolution->footline1)
        <tr>
            <td class="text-center">
                {!! $resolution->footline1 !!}
            </td>
        </tr>
    @endif
    @if($resolution->footline2)
        <tr>
            <td class="text-center">
                {!! $resolution->footline2 !!}
            </td>
        </tr>
    @endif
    @if($resolution->footline3)
        <tr>
            <td class="text-center">
                {!! $resolution->footline3 !!}
            </td>
        </tr>
    @endif
    @if($resolution->footline4)
        <tr>
            <td class="text-center">
                {!! $resolution->footline4 !!}
            </td>
        </tr>
    @endif
    <tr>
        <td class="text-center">
            Resolución Nª. <b>{{ $resolution->resolution_number }}</b> del {{ transformDate($resolution->date_from) }} al
            {{ transformDate($resolution->date_up) }} - Rango {{ $resolution->range_from }} al {{ $resolution->range_up }} - Prefijo {{ $resolution->prefix }},
            Vigencia: {{ getDiffInMonths($resolution->date_up, $resolution->date_from) }} Meses
        </td>
    </tr>
    @if(!$isQuitSignature)
    <tr>
        <td class="text-center">
            {{ env('SIGNATURE_COMPANY', 'MATIAS API - Desarrollado por: LOPEZSOFT S.A.S. - NIT: 901.091.403-2.') }} <br/>
            Documento generado y firmado electrónicamente, por <b>{{ env('APP_NAME') }}</b>, bajo la modalidad de Software propio.
        </td>
    </tr>
    @endif
</table>
