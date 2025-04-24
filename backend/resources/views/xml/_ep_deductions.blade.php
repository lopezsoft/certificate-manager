<Deducciones>
@if($deductions->health)
    <Salud Porcentaje="{{  number_format($deductions->health->percentage, 2, '.', '') }}" Deduccion="{{ number_format($deductions->health->deduction, 2, '.', '') }}" />
@endif
@if($deductions->pension_fund)
    <FondoPension Porcentaje="{{  number_format($deductions->pension_fund->percentage, 2, '.', '') }}" Deduccion="{{ number_format($deductions->pension_fund->deduction, 2, '.', '') }}" />
@endif
@if($deductions->fundSP)
    <FondoSP Porcentaje="{{ number_format($deductions->fundSP->percentage, 2, '.', '') ?? 0 }}" DeduccionSP="{{ number_format($deductions->fundSP->deduction ?? 0, 2, '.', '') }}"
             @if(isset($deductions->fundSP->percentageSub))
                 PorcentajeSub="{{ number_format($deductions->fundSP->percentageSub, 2, '.', '') ?? 0 }}"
             @endif
             @if(isset($deductions->fundSP->deductionSub))
                 DeduccionSub="{{ number_format($deductions->fundSP->deductionSub ?? 0, 2, '.', '') }}"
        @endif
    />
@endif
@if($deductions->trade_union)
    <Sindicatos>
        @foreach($deductions->trade_union as $un)
            <Sindicato Porcentaje="{{ $un->percentage }}" Deduccion="{{ number_format($un->deduction, 2, '.', '') }}" />
        @endforeach
    </Sindicatos>
@endif
@if($deductions->sanctions)
    <Sanciones>
        @foreach($deductions->sanctions as $san)
            <Sancion SancionPublic="{{ number_format($san->sanctionPublic, 2, '.', '') }}" SancionPriv="{{ number_format($san->sanctionPriv, 2, '.', '') }}" />
        @endforeach
    </Sanciones>
@endif
@if($deductions->libranzas)
    <Libranzas>
        @foreach($deductions->libranzas as $lib)
        <Libranza Descripcion="{{ $lib->description }}" Deduccion="{{ number_format($lib->deduction, 2, '.', '') }}" />
        @endforeach
    </Libranzas>
@endif
@if($deductions->third_party_payment)
    <PagosTerceros>
        @foreach($deductions->third_party_payment as $third)
        <PagoTercero>{{ number_format($third->third_party_pay, 2, '.', '') }}</PagoTercero>
        @endforeach
    </PagosTerceros>
@endif
@if($deductions->advances)
    <Anticipos>
        @foreach($deductions->advances as $adv)
        <Anticipo>{{ number_format($adv->advance, 2, '.', '') }}</Anticipo>
        @endforeach
    </Anticipos>
@endif
@if($deductions->other_deductions)
    <OtrasDeducciones>
        @foreach($deductions->other_deductions as $other)
        <OtraDeduccion>{{ number_format($other->other_deduction, 2, '.', '') }}</OtraDeduccion>
        @endforeach
    </OtrasDeducciones>
@endif
@if(isset($deductions->voluntary_pension))
    <PensionVoluntaria>{{ number_format($deductions->voluntary_pension, 2, '.', '') }}</PensionVoluntaria>
@endif
@if(isset($deductions->retefuente))
    <RetencionFuente>{{ number_format($deductions->retefuente, 2, '.', '') }}</RetencionFuente>
@endif
@if(isset($deductions->afc))
    <AFC>{{ number_format($deductions->afc, 2, '.', '') }}</AFC>
@endif
@if(isset($deductions->cooperative))
    <Cooperativa>{{ number_format($deductions->cooperative, 2, '.', '') }}</Cooperativa>
@endif
@if(isset($deductions->tax_embargo))
    <EmbargoFiscal>{{ number_format($deductions->tax_embargo, 2, '.', '') }}</EmbargoFiscal>
@endif
@if(isset($deductions->complementary_plan))
    <PlanComplementarios>{{ number_format($deductions->complementary_plan, 2, '.', '') }}</PlanComplementarios>
@endif
@if(isset($deductions->education))
    <Educacion>{{ number_format($deductions->education, 2, '.', '') }}</Educacion>
@endif
@if(isset($deductions->refund))
    <Reintegro>{{ number_format($deductions->refund, 2, '.', '') }}</Reintegro>
@endif
@if(isset($deductions->debt))
    <Deuda>{{ number_format($deductions->debt, 2, '.', '') }}</Deuda>
@endif
</Deducciones>
