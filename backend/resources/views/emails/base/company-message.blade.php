<div class="email-wrapper">
    <table class="table-company">
        <tr>
            <td>
                <table>
                    <tr>
                        <td>🏢 <span class="link-color p-text-justify">
                                {{ $messageData->company->company_name }} -
                                {{ $messageData->company->dni }}-{{ $messageData->company->dv }}
                            </span></td>
                    </tr>
                    <tr>
                        <td>📍<span class="p-text-justify">{{ $messageData->company->address }}</span></td>
                    </tr>
                    <tr>
                        <td>✉️ <span class="link-color p-text-justify">{{$messageData->company->email}}</span></td>
                    </tr>
                    <tr>
                        <td>📞 <span class="p-text-justify">{{ $messageData->company->mobile }} {{ $messageData->company->phone }}</span></td>
                    </tr>
                </table>
            </td>
            <td class="td-company">
                <img src="{{ $message->embed($messageData->company_image) }}"
                     alt="{{ $messageData->company->company_name}}" class="td-image">
            </td>
        </tr>
    </table>
</div>
