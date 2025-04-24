<Empleador RazonSocial="{{ $company->company_name }}"  NIT="{{ $company->dni }}" DV="{{ $company->dv }}"
    Pais="{{ $company->country->abbreviation_A2 }}" DepartamentoEstado="{{ $company->city->department->code }}"
    MunicipioCiudad="{{ $company->city->city_code }}" Direccion="{{ $company->address }}" />
