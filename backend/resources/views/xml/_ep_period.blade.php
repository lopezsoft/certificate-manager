@if($period)
	<Periodo FechaIngreso="{{ $period->date_entry }}"
        @if(isset($period->departure_date))
        FechaRetiro="{{ $period->departure_date }}"
        @endif
        FechaLiquidacionInicio="{{ $period->settlement_start_date }}" FechaLiquidacionFin="{{ $period->settlement_end_date }}"
        TiempoLaborado="{{ $period->time_worked }}" FechaGen="{{ $period->generation_date ?? Carbon\Carbon::now()->format('Y-m-d') }}"/>
@endif
