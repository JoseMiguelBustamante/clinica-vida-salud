@extends('layouts.clinic')

@section('title', 'Cancelar '.$appointment->codigo.' — Clínica Vida Salud')

@section('content')
    <section class="app-page" aria-labelledby="page-title">
        <p class="app-breadcrumb">Atención / Citas</p>
        <h1 id="page-title">Cancelar {{ $appointment->codigo }}</h1>
        <p class="lead">La cita permanece en el historial. El intervalo deja de ocupar disponibilidad.</p>

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
                    <dt>Médico</dt>
                    <dd>{{ $appointment->medico_apellidos }}, {{ $appointment->medico_nombres }} · {{ $appointment->especialidad_nombre }}</dd>
                </div>
                <div>
                    <dt>Intervalo</dt>
                    <dd>{{ \Illuminate\Support\Carbon::parse((string) $appointment->inicio)->timezone('UTC')->format('Y-m-d H:i') }}
                        – {{ \Illuminate\Support\Carbon::parse((string) $appointment->fin)->timezone('UTC')->format('H:i') }} UTC</dd>
                </div>
            </dl>
        </article>

        <form class="page-form" method="post" action="{{ route('appointments.cancel.store', $appointment->id_cita, false) }}">
            @csrf
            <div class="field">
                <label for="motivo_cancelacion">Motivo de cancelación (opcional)</label>
                <textarea id="motivo_cancelacion" name="motivo_cancelacion" maxlength="2000" rows="3">{{ old('motivo_cancelacion') }}</textarea>
            </div>
            <button class="button" type="submit">Confirmar cancelación</button>
            <p><a href="{{ route('appointments.show', $appointment->id_cita) }}">No cancelar</a></p>
        </form>
    </section>
@endsection
