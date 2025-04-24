@if($generationPlace)
    <LugarGeneracionXML Pais="{{ $generationPlace->country->abbreviation_A2 }}"
        DepartamentoEstado="{{ $generationPlace->department->code }}"
        MunicipioCiudad="{{ $generationPlace->city->city_code }}"
        Idioma="{{strtolower($language->ISO_639_1)}}" />
@endif
