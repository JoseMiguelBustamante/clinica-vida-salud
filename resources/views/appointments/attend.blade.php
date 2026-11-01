@extends('layouts.clinic')

@section('title', 'Atender '.$appointment->codigo.' — Clínica Vida Salud')

@section('content')
    <section class="app-page" aria-labelledby="page-title">
        <p class="app-breadcrumb">Atención / Citas</p>
        <h1 id="page-title">Marcar como atendida · {{ $appointment->codigo }}</h1>
        <p class="lead">Registra que la consulta se realizó. No exige diagnóstico ni receta. La llegada no sustituye este paso.</p>

        @if ($errors->any())
            <div class="form-errors" role="alert"><p>{{ $errors->first() }}</p></div>
        @endif

        <article class="list-card detail-card">
            <dl class="detail-list">
                <div>
                    <dt>Estado actual</dt>
                    <dd>{{ $appointment->estado }} · {{ $appointment->modalidad }}</dd>
                </div>
                <div>
                    <dt>Paciente</dt>
                    <dd>{{ $appointment->paciente_apellidos }}, {{ $appointment->paciente_nombres }}</dd>
                </div>
                <div>
                    <dt>Especialidad</dt>
                    <dd>{{ $appointment->especialidad_nombre }}</dd>
                </div>
                <div>
                    <dt>Intervalo</dt>
                    <dd>{{ \Illuminate\Support\Carbon::parse((string) $appointment->inicio)->timezone('UTC')->format('Y-m-d H:i') }}
                        – {{ \Illuminate\Support\Carbon::parse((string) $appointment->fin)->timezone('UTC')->format('H:i') }} UTC</dd>
                </div>
                <div>
                    <dt>Llegada</dt>
                    <dd>{{ isset($appointment->fecha_hora_llegada) && $appointment->fecha_hora_llegada
                        ? \Illuminate\Support\Carbon::parse((string) $appointment->fecha_hora_llegada)->timezone('UTC')->format('Y-m-d H:i')
                        : 'no registrada' }}</dd>
                </div>
            </dl>
        </article>

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
    </section>
@endsection
