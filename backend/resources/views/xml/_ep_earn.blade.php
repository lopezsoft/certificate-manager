<Devengados>
@if($earn->basic)
    <Basico DiasTrabajados="{{ $earn->basic->worked_days }}" SueldoTrabajado="{{ number_format($earn->basic->salary_worked, 2, '.', '') }}" />
@endif
@if($earn->transport)
    <Transporte
        @if(isset($earn->transport->transportation_assistance))
        AuxilioTransporte="{{ number_format($earn->transport->transportation_assistance ?? 0, 2, '.', '') }}"
        @endif
        @if(isset($earn->transport->viatic_maintenance))
        ViaticoManuAlojS="{{ number_format($earn->transport->viatic_maintenance ?? 0, 2, '.', '') }}"
        @endif
        @if(isset($earn->transport->viatic_non_salary_maintenance))
        ViaticoManuAlojNS="{{ number_format($earn->transport->viatic_non_salary_maintenance ?? 0, 2, '.', '') }}"
        @endif
         />
@endif
@if($earn->HEDs)
    <HEDs>
        @foreach ($earn->HEDs as $he)
        <HED Cantidad="{{ $he['amount'] }}" Porcentaje="{{ $he['percentage'] }}"
        Pago="{{ number_format($he['payment'], 2, '.', '') }}" />
        @endforeach
    </HEDs>
@endif
@if($earn->HENs)
    <HENs>
        @foreach ($earn->HENs as $he)
        <HEN Cantidad="{{ $he['amount'] }}" Porcentaje="{{ $he['percentage'] }}"
        Pago="{{ number_format($he['payment'], 2, '.', '') }}" />
        @endforeach
    </HENs>
@endif
@if($earn->HRNs)
    <HRNs>
        @foreach ($earn->HRNs as $he)
        <HRN Cantidad="{{ $he['amount'] }}" Porcentaje="{{ $he['percentage'] }}"
        Pago="{{ number_format($he['payment'], 2, '.', '') }}" />
        @endforeach
    </HRNs>
@endif
@if($earn->HEDDFs)
    <HEDDFs>
        @foreach ($earn->HEDDFs as $he)
        <HEDDF Cantidad="{{ $he['amount'] }}" Porcentaje="{{ $he['percentage'] }}"
        Pago="{{ number_format($he['payment'], 2, '.', '') }}" />
        @endforeach
    </HEDDFs>
@endif
@if($earn->HRDDFs)
    <HRDDFs>
        @foreach ($earn->HRDDFs as $he)
        <HRDDF Cantidad="{{ $he['amount'] }}" Porcentaje="{{ $he['percentage'] }}"
        Pago="{{ number_format($he['payment'], 2, '.', '') }}" />
        @endforeach
    </HRDDFs>
@endif
@if($earn->HENDFs)
    <HENDFs>
        @foreach ($earn->HENDFs as $he)
        <HENDF Cantidad="{{ $he['amount'] }}" Porcentaje="{{ $he['percentage'] }}"
        Pago="{{ number_format($he['payment'], 2, '.', '') }}" />
        @endforeach
    </HENDFs>
@endif
@if($earn->HRNDFs)
    <HRNDFs>
        @foreach ($earn->HRNDFs as $he)
        <HRNDF Cantidad="{{ $he['amount'] }}" Porcentaje="{{ $he['percentage'] }}"
        Pago="{{ number_format($he['payment'], 2, '.', '') }}" />
        @endforeach
    </HRNDFs>
@endif
@if($earn->vacations)
    <Vacaciones>
        @if(isset($earn->vacations->common))
        <VacacionesComunes FechaInicio="{{ $earn->vacations->common['start_date'] }}" FechaFin="{{ $earn->vacations->common['final_date'] }}"
            Cantidad="{{ $earn->vacations->common['amount'] }}" Pago="{{ number_format($earn->vacations->common['payment'], 2, '.', '') }}" />
        @endif
        @if(isset($earn->vacations->paid))
        <VacacionesCompensadas Cantidad="{{ $earn->vacations->paid['amount'] }}" Pago="{{ number_format($earn->vacations->paid['payment'], 2, '.', '') }}" />
        @endif
    </Vacaciones>
@endif
@if($earn->bonus)
    <Primas
    @if(isset($earn->bonus->amount))
        Cantidad="{{ $earn->bonus->amount }}"
    @endif
    @if(isset($earn->bonus->payment))
        Pago="{{ number_format($earn->bonus->payment, 2, '.', '') }}"
    @endif
    @if(isset($earn->bonus->non_salary_payment))
        PagoNS="{{ number_format($earn->bonus->non_salary_payment, 2, '.', '') }}"
    @endif
    />
@endif
@if($earn->cesantias)
    <Cesantias Pago="{{ number_format($earn->cesantias->payment, 2, '.', '')}}"
        Porcentaje="{{ $earn->cesantias->percentage }}" PagoIntereses="{{ number_format($earn->cesantias->interest_payment ?? 0, 2, '.', '') }}" />
@endif
@if($earn->incapacity)
    <Incapacidades>
        @foreach($earn->incapacity as $in)
        <Incapacidad FechaInicio="{{ $in->start_date }}" FechaFin="{{ $in->final_date }}" Cantidad="{{ $in->amount }}"
            Tipo="{{ $in->type->code }}" Pago="{{ number_format($in->payment,2, '.', '') }}" />
        @endforeach
    </Incapacidades>
@endif
@if($earn->licenses)
    <Licencias>
        @if(isset($earn->licenses->licenseMP))
        <LicenciaMP FechaInicio="{{ $earn->licenses->licenseMP->start_date }}"
                FechaFin="{{ $earn->licenses->licenseMP->final_date }}"
                Cantidad="{{ $earn->licenses->licenseMP->amount }}" Pago="{{ number_format($earn->licenses->licenseMP->payment, 2, '.', '') }}" />
        @endif
        @if(isset($earn->licenses->licenseR))
        <LicenciaR FechaInicio="{{ $earn->licenses->licenseR->start_date }}"
            FechaFin="{{ $earn->licenses->licenseR->final_date }}"
            Cantidad="{{ $earn->licenses->licenseR->amount }}" Pago="{{ number_format($earn->licenses->licenseR->payment, 2, '.', '') }}" />
        @endif
        @if(isset($earn->licenses->licenseNR))
        <LicenciaNR FechaInicio="{{ $earn->licenses->licenseNR->start_date }}"
            FechaFin="{{ $earn->licenses->licenseNR->final_date }}"
            Cantidad="{{ $earn->licenses->licenseNR->amount }}" />
        @endif
    </Licencias>
@endif
@if($earn->bonuses)
    <Bonificaciones>
        @foreach($earn->bonuses as $bon)
        <Bonificacion
            @if(isset($bon->bonusS))
                BonificacionS="{{ number_format($bon->bonusS ?? 0, 2, '.', '') }}"
            @endif
            @if(isset($bon->bonusNS))
                BonificacionNS="{{ number_format($bon->bonusNS ?? 0, 2, '.', '') }}"
            @endif
        />
        @endforeach
    </Bonificaciones>
@endif
@if($earn->assistances)
    <Auxilios>
        @foreach($earn->assistances as $assis)
            <Auxilio
            @if(isset($assis->assistanceS))
                AuxilioS="{{ number_format($assis->assistanceS, 2, '.', '') }}"
            @endif
            @if(isset($assis->assistanceNS))
                AuxilioNS="{{ number_format($assis->assistanceNS, 2, '.', '') }}"
            @endif
            />
        @endforeach
    </Auxilios>
@endif
@if($earn->legal_strikes)
    <HuelgasLegales>
        @foreach($earn->legal_strikes as $leg)
        <HuelgaLegal FechaInicio="{{ $leg->start_date }}" FechaFin="{{ $leg->final_date }}" Cantidad="{{ $leg->amount }}" />
        @endforeach
    </HuelgasLegales>
@endif
@if($earn->other_concepts)
    <OtrosConceptos>
        @foreach($earn->other_concepts as $other)
        <OtroConcepto DescripcionConcepto="{{ $other->descripcion }}"
            @if(isset($other->conceptS))
                ConceptoS="{{ number_format($other->conceptS, 2, '.', '')}}"
             @endif
            @if(isset($other->conceptNS))
                ConceptoNS="{{ number_format($other->conceptNS, 2, '.', '')}}"
            @endif
        />
        @endforeach
    </OtrosConceptos>
@endif
@if($earn->compensations)
    <Compensaciones>
        @foreach($earn->compensations as $comp)
        <Compensacion CompensacionO="{{ number_format($comp->compensationO, 2, '.', '')}}" CompensacionE="{{ number_format($comp->compensationE, 2, '.', '')}}" />
        @endforeach
    </Compensaciones>
@endif
@if($earn->bondEPCTVs)
    <BonoEPCTVs>
        @foreach($earn->bondEPCTVs as $bonu)
        <BonoEPCTV PagoS="{{ number_format($bonu->paymentS, 2, '.', '') }}" PagoNS="{{ number_format($bonu->paymentNS, 2, '.', '') }}"
            PagoAlimentacionS="{{ number_format($bonu->payment_foodS, 2, '.', '') }}" PagoAlimentacionNS="{{ number_format($bonu->payment_foodNS, 2, '.', '') }}" />
        @endforeach
    </BonoEPCTVs>
@endif
@if($earn->commissions)
    <Comisiones>
        @foreach($earn->commissions as $com)
        <Comision>{{ number_format($com->commission, 2, '.', '') }}</Comision>
        @endforeach
    </Comisiones>
@endif
@if($earn->payments_third_party)
    <PagosTerceros>
        @foreach($earn->payments_third_party as $pay)
        <PagoTercero>{{ number_format($pay->payment_third_party, 2, '.', '') }}</PagoTercero>
        @endforeach
    </PagosTerceros>
@endif
@if($earn->advances)
    <Anticipos>
        @foreach($earn->advances as $adv)
        <Anticipo>{{ number_format($adv->advance, 2, '.', '') }}</Anticipo>
        @endforeach
    </Anticipos>
@endif
@if(isset($earn->endowment))
    <Dotacion>{{ number_format($earn->endowment, 2, '.', '') }}</Dotacion>
@endif
@if(isset($earn->sustaining_support))
    <ApoyoSost>{{ number_format($earn->sustaining_support, 2, '.', '') }}</ApoyoSost>
@endif
@if(isset($earn->teleworking))
    <Teletrabajo>{{ number_format($earn->teleworking, 2, '.', '') }}</Teletrabajo>
@endif
@if(isset($earn->withdrawal_bonus))
    <BonifRetiro>{{ number_format($earn->withdrawal_bonus, 2, '.', '') }}</BonifRetiro>
@endif
@if(isset($earn->indemnification))
    <Indemnizacion>{{ number_format($earn->indemnification, 2, '.', '') }}</Indemnizacion>
@endif
@if(isset($earn->refund))
    <Reintegro>{{ number_format($earn->refund, 2, '.', '') }}</Reintegro>
@endif
</Devengados>
