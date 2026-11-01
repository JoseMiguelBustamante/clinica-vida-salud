@extends('layouts.clinic')

@section('title', 'Atender '.$appointment->codigo.' — Clínica Vida Salud')

@section('content')
    <section class="page-head" aria-labelledby="page-title">
        <p class="eyebrow">Citas</p>
        <h1 id="page-title">Marcar como atendida · {{ $appointment->codigo }}</h1>
        <p class="lead">Registra que la consulta se realizó. No exige diagnóstico ni receta. La llegada no sustituye este paso.</p>
    </section>

    @if ($errors->any())
        <div class="form-errors" role="alert"><p>{{ $errors->first() }}</p></div>
    @endif

    <section class="implementation">
        <p>Estado actual: {{ $appointment->estado }} · {{ $appointment->modalidad }}</p>
        <p>Paciente: {{ $appointment->paciente_apellidos }}, {{ $appointment->paciente_nombres }}</p>
        <p>Especialidad: {{ $appointment->especialidad_nombre }}</p>
        <p>Intervalo: {{ \Illuminate\Support\Carbon::parse((string) $appointment->inicio)->timezone('UTC')->format('Y-m-d H:i') }}
            – {{ \Illuminate\Support\Carbon::parse((string) $appointment->fin)->timezone('UTC')->format('H:i') }} UTC</p>
        <p>Llegada: {{ isset($appointment->fecha_hora_llegada) && $appointment->fecha_hora_llegada
            ? \Illuminate\Support\Carbon::parse((string) $appointment->fecha_hora_llegada)->timezone('UTC')->format('Y-m-d H:i')
            : 'no registrada' }}</p>
    </section>

    @if (in_array($appointment->estado, ['Programada', 'Confirmada'], true))
        <form class="page-form" method="post" action="{{ route('appointments.attend.store', $appointment->id_cita, false) }}">
            @csrf
            <div class="field">
                <label>
                    <input type="checkbox" name="verificado" value="1" required>
                    Confirmé que brindé la atención médica
                </label>
            </div>
            <div class="field">
                <label for="resumen_atencion">Resumen (opcional)</label>
                <textarea id="resumen_atencion" name="resumen_atencion" maxlength="2000" rows="3">{{ old('resumen_atencion') }}</textarea>
            </div>
            <div class="field">
                <label for="diagnostico">Diagnóstico (opcional)</label>
                <textarea id="diagnostico" name="diagnostico" maxlength="2000" rows="2">{{ old('diagnostico') }}</textarea>
            </div>
            <div class="field">
                <label for="indicaciones">Indicaciones (opcional)</label>
                <textarea id="indicaciones" name="indicaciones" maxlength="2000" rows="2">{{ old('indicaciones') }}</textarea>
            </div>
            <button class="button" type="submit">Marcar como atendida</button>
            <p><a href="{{ route('appointments.show', $appointment->id_cita) }}">No registrar</a></p>
        </form>
    @else
        <p>Esta cita ya está {{ $appointment->estado }}. No se repite el registro de atención.</p>
        <p><a href="{{ route('appointments.show', $appointment->id_cita) }}">Volver a la cita</a></p>
    @endif
@endsection
