@extends('layouts.clinic')

@section('title', 'Horario de atención — Clínica Vida Salud')

@section('content')
    <section class="app-page" aria-labelledby="page-title">
        <p class="app-breadcrumb">Atención / Horarios</p>
        <div class="page-toolbar">
            <div>
                <h1 id="page-title">{{ $schedule->nombres }} {{ $schedule->apellidos }}</h1>
                <p class="lead">
                    <span class="status-pill {{ $schedule->activo ? 'is-active' : 'is-inactive' }}">
                        {{ $schedule->activo ? 'Activo' : 'Inactivo' }}
                    </span>
                    · {{ \App\Modules\Schedules\ScheduleService::WEEKDAY_LABELS[$schedule->dia_semana] ?? $schedule->dia_semana }}
                    · {{ substr((string) $schedule->hora_inicio, 0, 5) }}–{{ substr((string) $schedule->hora_fin, 0, 5) }}
                </p>
            </div>
            <nav class="row-actions" aria-label="Acciones">
                <a class="row-action" href="{{ route('schedules.edit', $schedule->id_horario) }}">Modificar</a>
                <a class="row-action" href="{{ route('schedules.index') }}">Volver al listado</a>
            </nav>
        </div>

        @if (session('status'))
            <p class="form-status" role="status">{{ session('status') }}</p>
        @endif
        @if ($errors->any())
            <div class="form-errors" role="alert"><p>{{ $errors->first() }}</p></div>
        @endif

        <article class="list-card detail-card">
            <dl class="detail-list">
                <div>
                    <dt>Registro profesional</dt>
                    <dd>{{ $schedule->registro_profesional }}</dd>
                </div>
                <div>
                    <dt>Duración de cada cita</dt>
                    <dd>{{ $schedule->duracion_cita_minutos }} minutos</dd>
                </div>
            </dl>
        </article>

        @if ($schedule->activo)
            <form class="status-form" method="post" action="{{ route('schedules.deactivate', $schedule->id_horario, false) }}">
                @csrf
                <button class="button button-inline" type="submit">Desactivar</button>
            </form>
        @else
            <form class="status-form" method="post" action="{{ route('schedules.activate', $schedule->id_horario, false) }}">
                @csrf
                <button class="button button-inline" type="submit">Activar</button>
            </form>
        @endif
    </section>
@endsection
