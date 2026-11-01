@extends('layouts.clinic')

@section('title', 'Asistencia '.$appointment->codigo.' — Clínica Vida Salud')

@section('content')
    <section class="app-page" aria-labelledby="page-title">
        <p class="app-breadcrumb">Atención / Citas</p>
        <h1 id="page-title">Confirmación y asistencia · {{ $appointment->codigo }}</h1>
        <p class="lead">La confirmación, la llegada y la inasistencia son hechos distintos de la atención médica.</p>

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
                <div>
                    <dt>Llegada</dt>
                    <dd>{{ $appointment->fecha_hora_llegada
                        ? \Illuminate\Support\Carbon::parse((string) $appointment->fecha_hora_llegada)->timezone('UTC')->format('Y-m-d H:i')
                        : 'no registrada' }}</dd>
                </div>
            </dl>
        </article>

        @if ($appointment->estado === 'Programada')
            <form class="page-form" method="post" action="{{ route('appointments.confirm', $appointment->id_cita, false) }}">
                @csrf
                <p>Registra la intención de asistir comunicada por el paciente. No acredita presencia ni atención.</p>
                <button class="button" type="submit">Registrar confirmación</button>
            </form>
        @endif

        @if (in_array($appointment->estado, ['Programada', 'Confirmada'], true) && ! $appointment->fecha_hora_llegada)
            <form class="page-form" method="post" action="{{ route('appointments.arrival', $appointment->id_cita, false) }}">
                @csrf
                <p>Registra que el paciente se presentó. No cambia la cita a Atendida.</p>
                <button class="button" type="submit">Registrar llegada</button>
            </form>
        @endif

        @if (in_array($appointment->estado, ['Programada', 'Confirmada'], true) && ! $appointment->fecha_hora_llegada)
            <form class="page-form" method="post" action="{{ route('appointments.absence', $appointment->id_cita, false) }}">
                @csrf
                <p>Solo después del fin del intervalo y si verificó que el paciente no se presentó. El tiempo no lo marca solo.</p>
                <div class="field">
                    <label>
                        <input type="checkbox" name="verificado" value="1" required>
                        Verifiqué que el paciente no se presentó
                    </label>
                </div>
                <button class="button" type="submit">Registrar inasistencia</button>
            </form>
        @endif

        <p><a href="{{ route('appointments.show', $appointment->id_cita) }}">Volver a la cita</a></p>
    </section>
@endsection
