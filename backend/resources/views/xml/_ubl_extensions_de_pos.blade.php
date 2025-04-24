@isset($softwareManufacturer)
<ext:UBLExtension>
    <ext:ExtensionContent>
        <FabricanteSoftware>
            <InformacionDelFabricanteDelSoftware>
                <Name>NombreApellido</Name>
                <Value>{{$softwareManufacturer->owner_name}}</Value>
                <Name>RazonSocial</Name>
                <Value>{{$softwareManufacturer->company_name}}</Value>
                <Name>NombreSoftware</Name>
                <Value>{{$softwareManufacturer->software_name}}</Value>
            </InformacionDelFabricanteDelSoftware>
        </FabricanteSoftware>
    </ext:ExtensionContent>
</ext:UBLExtension>
@endisset
@isset($showroomInformation)
<ext:UBLExtension>
    <ext:ExtensionContent>
        <ShowroomInformation>
            <Showroom>{{ $showroomInformation->showroom }}</Showroom>
            <ShowroomAddress>{{ $showroomInformation->showroomAddress }}</ShowroomAddress>
            <DataShow>
                <ExhibitionRoom>{{ $showroomInformation->dataShow['exhibitionRoom'] }}</ExhibitionRoom>
                <TotalChairs>{{ $showroomInformation->dataShow['totalChairs'] }}</TotalChairs>
                <NameFunction>{{ $showroomInformation->dataShow['nameFunction'] }}</NameFunction>
                <SelectLocation>{{ $showroomInformation->dataShow['selectLocation'] }}</SelectLocation>
                <DateFunction>{{ $showroomInformation->dataShow['dateFunction'] }}</DateFunction>
                <TimeFunction>{{ $showroomInformation->dataShow['timeFunction'] }}</TimeFunction>
            </DataShow>
        </ShowroomInformation>
    </ext:ExtensionContent>
</ext:UBLExtension>
@endisset
@if(in_array($software->type_id, [4]))
<ext:UBLExtension>
    <ext:ExtensionContent>
        <BeneficiosComprador>
            <InformacionBeneficiosComprador>
                <Name>Codigo</Name>
                <Value>{{$customer->company->dni}}</Value>
                <Name>NombresApellidos</Name>
                <Value>{{$customer->company->company_name}}</Value>
                <Name>Puntos</Name>
                <Value>{{$customer->points ?? 0}}</Value>
            </InformacionBeneficiosComprador>
        </BeneficiosComprador>
    </ext:ExtensionContent>
</ext:UBLExtension>
@endif
@isset($pointsOfSale)
<ext:UBLExtension>
    <ext:ExtensionContent>
        <PuntoVenta>
            <InformacionCajaVenta>
                <Name>PlacaCaja</Name>
                <Value>{{$pointsOfSale->terminal_number}}</Value>
                <Name>UbicaciónCaja</Name>
                <Value>{{$pointsOfSale->address}}</Value>
                <Name>Cajero</Name>
                <Value>{{$pointsOfSale->cashier_name}}</Value>
                <Name>TipoCaja</Name>
                <Value>{{$pointsOfSale->cashier_type}}</Value>
                <Name>CódigoVenta</Name>
                <Value>{{$pointsOfSale->sales_code}}</Value>
                <Name>SubTotal</Name>
                <Value>{{number_format($pointsOfSale->sub_total ?? 0, 2, '.', ',')}}</Value>
            </InformacionCajaVenta>
        </PuntoVenta>
    </ext:ExtensionContent>
</ext:UBLExtension>
@endisset
