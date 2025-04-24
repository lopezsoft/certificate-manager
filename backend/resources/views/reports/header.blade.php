<table class="header-invoice">
    <tr>
        <td>
            <img src="{{ $logo }}" alt="" class="img-invoice">
        </td>
        <td>
            @if (isset($headerLine1))
                {!! $headerLine1 !!}
            @endif
            @if (isset($headerLine2))
                @if (Str::length($headerLine2) > 5)
                    {!! $headerLine2 !!}
                @endif
            @endif
        </td>
    </tr>
</table>
