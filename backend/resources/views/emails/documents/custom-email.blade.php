<x-mail::message>
<div class="email-wrapper">
<table class="table-notification">
    <tr>
        <td><span class="link-color">{{strtoupper($messageData->title)}}</span></td>
        <td class="td-icon">
            <img src="https://matias.com.co/assets/img/brand/invoice-app.png" alt="">
        </td>
    </tr>
</table>
<p class="p-text-justify">
    {!! $messageData->message !!}
</p>
@include('emails.base.info')
</div>
<br>
@include('emails.base.company-message')
</x-mail::message>
