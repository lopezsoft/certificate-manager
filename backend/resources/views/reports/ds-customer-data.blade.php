<table class="table-customer">
    <tr>
        <th>Tipo de documento</th>
        <td>{{ $saleMaster->customer->identityDocument->document_name ?? '' }}</td>
        <th>Número de documento</th>
        <td>{{ "{$saleMaster->customer->dni}" }}</td>
    </tr>
    <tr>
        <th>Nombre o razón social</th>
        <td>{{ $saleMaster->customer->company_name }}</td>
    </tr>
    <tr>
        <th>Tipo de contribuyente</th>
        <td>{{ $saleMaster->customer->typeOrganization->description ?? '' }}</td>
        <th>Régimen fiscal</th>
        <td>{{ $saleMaster->customer->taxRegime->description ?? '' }}</td>
    </tr>
    <tr>
        <th>Responsabilidad tributaria</th>
        <td>{{ $saleMaster->customer->taxLevel->description ?? '' }}</td>
        <th>Pais</th>
        <td>{{ $saleMaster->customer->country->country_name ?? '' }}</td>
    </tr>
    <tr>
        <th>Ciudad</th>
        <td>{{ $saleMaster->customer->city_name ?? '' }}</td>
        <th>Dirección</th>
        <td>{{ $saleMaster->customer->address ?? '' }}</td>
    </tr>
</table>
