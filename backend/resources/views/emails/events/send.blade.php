<x-mail::message>
    <div class="email-wrapper">
        <table class="table-notification">
            <tr>
                <td><span class="link-color">{{strtoupper($messageData->event_name)}}</span></td>
                <td class="td-icon">
                    <img src="https://matias.com.co/assets/img/brand/invoice-app.png" alt="">
                </td>
            </tr>
        </table>
        <p class="p-text-justify">
            🏫 Hola <strong>{{$messageData->company_name}}</strong>.<br>
            📝 Se ha generado un evento al documento que relacionamos a
            continuación:<br>
            🔖 Documento Nº. <strong>{{ $messageData->document_nro }}</strong><br>
            💰 Total: <strong>{{ $messageData->total }}</strong>
        </p>
        <br/>
        <p class="p-text-justify">
            📬 Adjunto encontrará el documento relacionado con el evento, en un archivo comprimido.
        </p>
        <br>
        @include('emails.base.info')
    </div>
    <br>
    @include('emails.base.company-message')
</x-mail::message>
