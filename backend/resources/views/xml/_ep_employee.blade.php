@if($employee)
   <Trabajador TipoTrabajador="{{ $employee->worker_type->code }}" SubTipoTrabajador="{{ $employee->worker_subtype->code }}"
        AltoRiesgoPension="{{ $employee->high_risk_pension }}" TipoDocumento="{{ $employee->identity_document->code }}" NumeroDocumento="{{ $employee->document_number }}"
        PrimerApellido="{{ $employee->first_surname }}" SegundoApellido="{{ $employee->second_surname }}" PrimerNombre="{{ $employee->first_name }}"
        @if($employee->other_names) OtrosNombres="{{ $employee->other_names }}" @endif LugarTrabajoPais="{{ $employee->working_country->abbreviation_A2 }}"
        LugarTrabajoDepartamentoEstado="{{ $employee->work_city->department->code }}" LugarTrabajoMunicipioCiudad="{{ $employee->work_city->city_code }}"
        LugarTrabajoDireccion="{{ $employee->work_address }}" SalarioIntegral="{{ $employee->integral_salary }}"
        TipoContrato="{{ $employee->contract_type->code }}" Sueldo="{{ number_format($employee->salary,2, '.', '')}}" CodigoTrabajador="{{ $employee->worker_code }}" />
@endif
