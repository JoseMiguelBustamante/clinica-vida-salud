@extends('layouts.clinic')

@section('title', 'Horarios de atención — Clínica Vida Salud')

@section('content')
    <section class="page-head" aria-labelledby="page-title">
        <p class="eyebrow">Disponibilidad</p>
        <h1 id="page-title">Horarios de atención</h1>
        <p class="lead">Día, franja y duración de citas por médico. Desactivar no borra citas existentes.</p>
        <p><a class="button button-inline" href="{{ route('schedules.create') }}">Registrar horario</a></p>
    </section>

    @if (session('status'))
        <p class="form-errors" role="status">{{ session('status') }}</p>
    @endif
    @if ($errors->any())
        <div class="form-errors" role="alert"><p>{{ $errors->first() }}</p></div>
    @endif

    <form class="search-bar" method="get" action="{{ route('schedules.index', absolute: false) }}">
        <div class="field">
            <label for="q">Buscar por médico, registro o día</label>
            <input id="q" name="q" type="search" value="{{ $q }}">
        </div>
        <button class="button button-inline" type="submit">Buscar</button>
    </form>

    <div class="table-wrap">
        <table class="data-table">
            <thead>
                <tr>
                    <th>Médico</th>
                    <th>Día</th>
                    <th>Franja</th>
                    <th>Duración</th>
                    <th>Estado</th>
                    <th>Acciones</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($schedules as $schedule)
                    <tr>
                        <td>{{ $schedule->nombres }} {{ $schedule->apellidos }}</td>
                        <td>{{ \App\Modules\Schedules\ScheduleService::WEEKDAY_LABELS[$schedule->dia_semana] ?? $schedule->dia_semana }}</td>
                        <td>{{ substr((string) $schedule->hora_inicio, 0, 5) }}–{{ substr((string) $schedule->hora_fin, 0, 5) }}</td>
                        <td>{{ $schedule->duracion_cita_minutos }} min</td>
                        <td>{{ $schedule->activo ? 'Activo' : 'Inactivo' }}</td>
                        <td>
                            <a href="{{ route('schedules.show', $schedule->id_horario) }}">Ver</a>
                            ·
                            <a href="{{ route('schedules.edit', $schedule->id_horario) }}">Modificar</a>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="6">No hay horarios que coincidan con la búsqueda.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
@endsection
