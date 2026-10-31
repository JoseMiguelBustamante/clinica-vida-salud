@extends('layouts.clinic')

@section('title', 'Cancelar '.$appointment->codigo.' — Clínica Vida Salud')

@section('content')
    <section class="page-head" aria-labelledby="page-title">
        <p class="eyebrow">Citas</p>
        <h1 id="page-title">Cancelar {{ $appointment->codigo }}</h1>
        <p class="lead">La cita permanece en el historial. El intervalo deja de ocupar disponibilidad.</p>
    </section>

    @if ($errors->any())
        <div class="form-errors" role="alert"><p>{{ $errors->first() }}</p></div>
    @endif

    <section class="implementation">
        <p>Estado actual: {{ $appointment->estado }} · {{ $appointment->modalidad }}</p>
        <p>Paciente: {{ $appointment->paciente_apellidos }}, {{ $appointment->paciente_nombres }}</p>
        <p>Médico: {{ $appointment->medico_apellidos }}, {{ $appointment->medico_nombres }} · {{ $appointment->especialidad_nombre }}</p>
        <p>Intervalo: {{ \Illuminate\Support\Carbon::parse((string) $appointment->inicio)->timezone('UTC')->format('Y-m-d H:i') }}
            – {{ \Illuminate\Support\Carbon::parse((string) $appointment->fin)->timezone('UTC')->format('H:i') }} UTC</p>
    </section>

    <form class="page-form" method="post" action="{{ route('appointments.cancel.store', $appointment->id_cita, false) }}">
        @csrf
        <div class="field">
            <label for="motivo_cancelacion">Motivo de cancelación (opcional)</label>
            <textarea id="motivo_cancelacion" name="motivo_cancelacion" maxlength="2000" rows="3">{{ old('motivo_cancelacion') }}</textarea>
        </div>
        <button class="button" type="submit">Confirmar cancelación</button>
        <p><a href="{{ route('appointments.show', $appointment->id_cita) }}">No cancelar</a></p>
    </form>
@endsection
