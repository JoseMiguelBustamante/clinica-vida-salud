@extends('layouts.clinic')

@section('title', 'Horario de atención — Clínica Vida Salud')

@section('content')
    <section class="page-head" aria-labelledby="page-title">
        <p class="eyebrow">Disponibilidad</p>
        <h1 id="page-title">{{ $schedule->nombres }} {{ $schedule->apellidos }}</h1>
        <p class="lead">{{ $schedule->activo ? 'Activo' : 'Inactivo' }}
            · {{ \App\Modules\Schedules\ScheduleService::WEEKDAY_LABELS[$schedule->dia_semana] ?? $schedule->dia_semana }}
            · {{ substr((string) $schedule->hora_inicio, 0, 5) }}–{{ substr((string) $schedule->hora_fin, 0, 5) }}</p>
    </section>

    @if (session('status'))
        <p class="form-errors" role="status">{{ session('status') }}</p>
    @endif
    @if ($errors->any())
        <div class="form-errors" role="alert"><p>{{ $errors->first() }}</p></div>
    @endif

    <section class="implementation">
        <p>Registro profesional: {{ $schedule->registro_profesional }}</p>
        <p>Duración de cada cita: {{ $schedule->duracion_cita_minutos }} minutos</p>
        <p><a href="{{ route('schedules.edit', $schedule->id_horario) }}">Modificar</a>
            · <a href="{{ route('schedules.index') }}">Volver al listado</a></p>
        @if ($schedule->activo)
            <form method="post" action="{{ route('schedules.deactivate', $schedule->id_horario, false) }}">
                @csrf
                <button class="button button-inline" type="submit">Desactivar</button>
            </form>
        @else
            <form method="post" action="{{ route('schedules.activate', $schedule->id_horario, false) }}">
                @csrf
                <button class="button button-inline" type="submit">Activar</button>
            </form>
        @endif
    </section>
@endsection
