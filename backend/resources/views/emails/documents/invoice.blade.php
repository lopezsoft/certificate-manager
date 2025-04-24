<x-mail::message>
<div class="email-wrapper">
<table class="table-notification">
    <tr>
        <td><span class="link-color">{{strtoupper($messageData->invoice_name)}}</span></td>
        <td class="td-icon">
            <img src="https://matias.com.co/assets/img/brand/invoice-app.png" alt="">
        </td>
    </tr>
</table>
<p class="p-text-justify">
🏫 Hola <strong>{{$messageData->company_name}}</strong>.<br>
📝 A su nombre se ha generado un documento electrónico desde nuestro sistema, el cual relacionamos a continuación:<br>
🔖 Documento Nº. <strong>{{ $messageData->invoice_nro }}</strong><br>
💰 Total: <strong>{{ $messageData->total }}</strong>
</p>
@if(!is_null($messageData->url))
<x-mail::button :url="$messageData->url">
    Descargar documento del portal DIAN
</x-mail::button>
@endif
{{--<x-mail::button :url="''">
   Ver factura
</x-mail::button>--}}
<p class="p-text-justify">📬 Para notificar los eventos: acuse de recibo, aceptación, rechazo o cualquier información relacionada con este documento, se relaciona la siguiente dirección de correo auto respuesta:<br>
<a href="mailto:{{$messageData->company->email}}" class="link-color">{{$messageData->company->email}}</a></p>

@include('emails.base.info')
</div>
<br>
@include('emails.base.company-message')
</x-mail::message>
