<ProveedorXML RazonSocial="{{ $company->company_name }}"  NIT="{{ $company->dni }}" DV="{{ $company->dv }}"
    SoftwareID="{{ $software->identification }}"
    SoftwareSC="{{ hash('sha384', "{$software->identification}{$software->pin}{$sequenceNumber->number}") }}" />
