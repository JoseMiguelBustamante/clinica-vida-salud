@extends('layouts.clinic')

@section('title', 'Disponibilidad — Clínica Vida Salud')

@section('content')
    <section class="page-head" aria-labelledby="page-title">
        <p class="eyebrow">Citas</p>
        <h1 id="page-title">Médicos y disponibilidad</h1>
        <p class="lead">Consulte intervalos libres. Esta pantalla no reserva ni registra una cita.</p>
    </section>

    @if ($status)
        <p class="form-errors" role="alert">{{ $status }}</p>
    @endif
    @if ($errors->any())
        <div class="form-errors" role="alert"><p>{{ $errors->first() }}</p></div>
    @endif

    <form class="page-form" method="get" action="{{ route('availability.consult', absolute: false) }}">
        <div class="field">
            <label for="especialidad">Especialidad</label>
            <select id="especialidad" name="especialidad">
                <option value="">Seleccione</option>
                @foreach ($specialties as $specialty)
                    <option value="{{ $specialty->id_especialidad }}" @selected((string) $especialidad === (string) $specialty->id_especialidad)>
                        {{ $specialty->nombre }}
                    </option>
                @endforeach
            </select>
        </div>
        <div class="field">
            <label for="medico">Médico</label>
            @if ($especialidad && $physicians->isEmpty())
                <p>No hay médicos activos para esa especialidad.</p>
            @endif
            <select id="medico" name="medico">
                <option value="">Seleccione</option>
                @foreach ($physicians as $physician)
                    <option value="{{ $physician->id_medico }}" @selected((string) $medico === (string) $physician->id_medico)>
                        {{ $physician->apellidos }}, {{ $physician->nombres }} · {{ $physician->registro_profesional }}
                    </option>
                @endforeach
            </select>
        </div>
        <div class="field">
            <label for="fecha">Fecha</label>
            <input id="fecha" name="fecha" type="date" value="{{ $fecha }}" min="{{ now()->toDateString() }}">
        </div>
        <button class="button" type="submit">Consultar</button>
    </form>

    @if ($slots !== null)
        <section class="implementation" aria-labelledby="slots-title">
            <h2 id="slots-title">Intervalos disponibles</h2>
            @if ($slots === [])
                <p>No hay intervalos disponibles para esos criterios. Pruebe otra fecha o médico.</p>
            @else
                <ul class="slot-list">
                    @foreach ($slots as $slot)
                        <li>{{ $slot['hora_inicio'] }}–{{ $slot['hora_fin'] }}</li>
                    @endforeach
                </ul>
                <p>Seleccionar un intervalo aquí no lo reserva. La programación de citas aún no está implementada.</p>
            @endif
        </section>
    @endif
@endsection
