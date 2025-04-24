<table class="table-customer">
    @if($isFinalConsumer)
    <tr>
        <th>Nombre o razón social</th>
        <td>{{ $saleMaster->customer->company_name }}</td>
        <th>Número de documento</th>
        <td>{{ $saleMaster->customer->dni }}</td>
    </tr>
    @else
    <tr>
        <th>Tipo de documento</th>
        <td>{{ $saleMaster->customer->identityDocument->document_name ?? '' }}</td>
        <th>Teléfono</th>
        <td>{{ $saleMaster->customer->mobile ?? '' }} {{$saleMaster->customer->phone ?? ''}}</td>
    </tr>
    <tr>
        <th>Número de documento</th>
        <td>{{ "{$saleMaster->customer->dni} {$saleMaster->customer->dv}" }}</td>
        <th>Correo</th>
        <td>{{ $saleMaster->customer->email ?? '' }}</td>
    </tr>
    <tr>
        <th>Nombre o razón social</th>
        <td>{{ $saleMaster->customer->company_name }}</td>
        <th>Pais</th>
        <td>{{ $saleMaster->customer->country->country_name ?? '' }}</td>
    </tr>
    <tr>
        <th>Tipo de contribuyente</th>
        <td>{{ $saleMaster->customer->typeOrganization->description ?? '' }}</td>
        <th>Departamento</th>
        <td>{{ $saleMaster->customer->city->department->name_department ?? '' }}</td>
    </tr>
    <tr>
        <th>Régimen fiscal</th>
        <td>({{$saleMaster->customer->taxRegime->code ?? ''}}){{ $saleMaster->customer->taxRegime->description ?? '' }}</td>
        <th>Ciudad</th>
        <td>{{ $saleMaster->customer->city_name ?? $saleMaster->customer->city->name_city ?? '' }}</td>
    </tr>
    <tr>
        <th>Responsabilidad tributaria</th>
        <td>({{$saleMaster->customer->taxLevel->code ?? ''}}){{ $saleMaster->customer->taxLevel->description ?? '' }}</td>
        <th>Dirección</th>
        <td>{{ $saleMaster->customer->address ?? '' }}</td>
    </tr>
    @endif
</table>
