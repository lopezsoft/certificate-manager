<x-mail::message>
<div class="email-wrapper">
<table class="table-notification">
    <tr>
        <td><span class="link-color">{{strtoupper("registro de usuario")}}</span></td>
        <td class="td-icon">
            <img src="https://matias.com.co/assets/img/brand/user-info.png" alt="">
        </td>
    </tr>
</table>
<p class="p-text-justify">
🏫 Hola <strong>{{$messageData->company_name}}</strong>.<br>
📝 A sido registrado en nuestro sistema, sus datos de acceso son los siguientes:<br>
🧑‍💻 Usuario:. <strong>{{ $messageData->email }}</strong><br>
🗝️ Contraseña: <strong>{{ $messageData->password }}</strong>
</p>

@include('emails.base.info')
</div>
<br>
@include('emails.base.company-message')
</x-mail::message>
