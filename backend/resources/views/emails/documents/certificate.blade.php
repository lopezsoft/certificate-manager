<x-mail::message>
    <div class="email-wrapper">
        <table class="table-notification">
            <tr>
                <td><span class="link-color">{{strtoupper($messageData->data->company_name)}}</span></td>
                <td class="td-icon">
                    <img src="https://matias.com.co/assets/img/brand/invoice-app.png" alt="">
                </td>
            </tr>
        </table>
        <p class="p-text-justify">
            <br/>
            👋 <b>¡Cordial saludo, estimado proveedor!</b>
            <br/>
            <br/>
            📝 Solicitamos de manera respetuosa la emisión de:
            <br/>
            <b>CERTIFICADO PARA FACTURACIÓN ELECTRÓNICA</b> 🧾
            <br/>
            <br/>
            📅 <b>Vigencia del certificado</b>: {{$messageData->data->life}} año(s)
            <br/>
            <br/>
            👤 <b>Representante legal del certificado</b>:
            <br/>
            {{$messageData->data->legal_representative}}
            <br/>
            🔖 <b> Número de documento</b>: {{$messageData->data->document_number}}
            <br/>
            <br/>
            🏢 <b>Datos empresariales:</b>
            <br/>
            - <b>Tipo de contribuyente</b>: {{$messageData->data->organization->description}}
            <br/>
            - <b>Nombre o Razón Social</b>:
            <br/>
            {{$messageData->data->company_name}}
            <br/>
            - <b>NIT</b>: {{$messageData->data->dni}}-{{$messageData->data->dv}}
            <br/>
            - <b>Ciudad</b>: {{$messageData->data->city->name_city}}
            <br/>
            - <b>Dirección</b>: {{$messageData->data->address}}
        </p>
        <br/>
        <p class="p-text-justify">
            📬 Adjunto encontrará la documentación requerida para la emisión del mismo.
        </p>
        <p class="p-text-justify">🚫 Este mensaje fue enviado automáticamente desde nuestro sistema.</p>
    </div>
    <br/>
</x-mail::message>
