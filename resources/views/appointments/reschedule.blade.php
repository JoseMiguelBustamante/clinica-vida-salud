@extends('layouts.clinic')

@section('title', 'Reprogramar '.$appointment->codigo.' — Clínica Vida Salud')

@section('content')
    <section class="page-head" aria-labelledby="page-title">
        <p class="eyebrow">Citas</p>
        <h1 id="page-title">Reprogramar {{ $appointment->codigo }}</h1>
        <p class="lead">Se conserva el mismo identificador. Si la cita está Confirmada, volverá a Programada y hará falta una nueva confirmación.</p>
    </section>

    @if ($status)
        <p class="form-errors" role="alert">{{ $status }}</p>
    @endif
    @if ($errors->any())
        <div class="form-errors" role="alert"><p>{{ $errors->first() }}</p></div>
    @endif

    <section class="implementation">
        <p>Estado actual: {{ $appointment->estado }} · {{ $appointment->modalidad }}</p>
        <p>Paciente: {{ $appointment->paciente_apellidos }}, {{ $appointment->paciente_nombres }}</p>
        <p>Médico: {{ $appointment->medico_apellidos }}, {{ $appointment->medico_nombres }} · {{ $appointment->especialidad_nombre }}</p>
        <p>Intervalo actual: {{ \Illuminate\Support\Carbon::parse((string) $appointment->inicio)->timezone('UTC')->format('Y-m-d H:i') }}
            – {{ \Illuminate\Support\Carbon::parse((string) $appointment->fin)->timezone('UTC')->format('H:i') }} UTC</p>
    </section>

    <form class="search-bar" method="get" action="{{ route('appointments.reschedule', $appointment->id_cita, false) }}">
        <div class="field">
            <label for="consulta_fecha">Consultar intervalos libres</label>
            <input id="consulta_fecha" name="fecha" type="date" value="{{ $fecha }}" min="{{ now()->toDateString() }}">
        </div>
        <button class="button button-inline" type="submit">Consultar</button>
    </form>

    @if ($slots !== null)
        <section class="implementation" aria-labelledby="slots-title">
            <h2 id="slots-title">Intervalos disponibles</h2>
            @if ($slots === [])
                <p>No hay intervalos disponibles para esa fecha. Pruebe otra.</p>
            @else
                <ul class="slot-list">
                    @foreach ($slots as $slot)
                        <li>
                                <a href="{{ route('appointments.reschedule', [
                                    'cita' => $appointment->id_cita,
                                    'fecha' => $fecha,
                                    'hora_inicio' => $slot['hora_inicio'],
                                    'hora_fin' => $slot['hora_fin'],
                                ], false) }}">{{ $slot['hora_inicio'] }}–{{ $slot['hora_fin'] }}</a>
                        </li>
                    @endforeach
                </ul>
                <p>Seleccionar un intervalo no lo reserva. La cita se actualiza solo al confirmar.</p>
            @endif
        </section>
    @endif

    <form id="reprogramar" class="page-form" method="post" action="{{ route('appointments.reschedule.store', $appointment->id_cita, false) }}" novalidate>
        @csrf
        <div class="field">
            <label for="fecha">Nueva fecha</label>
            <input id="fecha" name="fecha" type="date" required value="{{ old('fecha', $fecha) }}" min="{{ now()->toDateString() }}">
        </div>
        <div class="field">
            <label for="hora_inicio">Hora de inicio</label>
            <input id="hora_inicio" name="hora_inicio" type="time" required value="{{ old('hora_inicio', $hora_inicio) }}">
        </div>
        <div class="field">
            <label for="hora_fin">Hora de fin</label>
            <input id="hora_fin" name="hora_fin" type="time" required value="{{ old('hora_fin', $hora_fin) }}">
        </div>
        <button class="button" type="submit">Confirmar reprogramación</button>
        <p><a href="{{ route('appointments.show', $appointment->id_cita) }}">Cancelar</a></p>
    </form>
@endsection
