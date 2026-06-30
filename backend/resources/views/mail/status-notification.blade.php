<x-mail::message>
    <div class="email-wrapper">
        Hola, <b>{{ $data->company->company_name }}</b>
        <br/>
        <p class="p-text-justify">
            👋 <b>¡Cordial saludo, estimado cliente!</b>
            <br/>
            📝 Le informamos que la solicitud del certificado ha cambiado de estado.
            <br/>
            Estado de solicitud: <b>{{ \App\Enums\CertificateRequestStatusEnum::tryFrom($data->request_status)?->description() ?? $data->request_status }}</b>
        </p>
        <br/>
        <b>Cliente de la solicitud: </b>
        <br/>
        <p class="p-text-justify">
            <b>Nombre o Razón Social:</b>
            <br/>
            {{ $data->data->company_name }}
            <br/>
            <b>NIT:</b>
            <br/>
            {{ $data->data->dni }}-{{ $data->data->dv }}
        </p>
        <b>Comentarios:</b>
        <br/>
        <div class="p-text-justify">
            {!! $data->comments !!}
        </div>
        <x-mail::button :url="url('/')">
            Abrir sistema
        </x-mail::button>
        <p class="p-text-justify">
            🚫 Este mensaje fue enviado automáticamente desde nuestro sistema.
            <br/>
            Por favor no responda a este correo.
        </p>
    </div>
</x-mail::message>
