@extends('layouts.clinic')

@section('title', 'Citas — Clínica Vida Salud')

@php
    $shown = $appointments->count();
    $P = \App\Modules\Identity\PermissionCatalog::class;
    $codes = $identityPermissions ?? [];
    $has = static fn (string $code): bool => in_array($code, $codes, true);
    $months = [1 => 'ene', 2 => 'feb', 3 => 'mar', 4 => 'abr', 5 => 'may', 6 => 'jun', 7 => 'jul', 8 => 'ago', 9 => 'sep', 10 => 'oct', 11 => 'nov', 12 => 'dic'];
    $programarHref = $has($P::CONSULT_AVAILABILITY)
        ? route('availability.consult', ['nueva' => 1], false)
        : route('appointments.create', absolute: false);
@endphp

@section('content')
    <section class="app-page" aria-labelledby="page-title">
        <p class="app-breadcrumb">Atención / Citas</p>
        <div class="page-toolbar">
            <div>
                <h1 id="page-title">Citas registradas</h1>
                <p class="lead">Consulta y gestiona las citas de tus pacientes.</p>
            </div>
            @if ($has($P::BOOK_APPOINTMENTS))
                <a class="button button-inline button-add" href="{{ $programarHref }}">Programar cita</a>
            @endif
        </div>

        @if (session('status'))
            <p class="form-status" role="status">{{ session('status') }}</p>
        @endif
        @if ($errors->any())
            <div class="form-errors" role="alert"><p>{{ $errors->first() }}</p></div>
        @endif

        <div class="list-card">
            <form class="filter-bar appointment-filters" method="get" action="{{ route('appointments.index', absolute: false) }}">
                <div class="field">
                    <label for="q">Buscar por código, paciente o médico</label>
                    <input id="q" name="q" type="search" value="{{ $q }}" placeholder="Código, paciente o médico">
                </div>
                <div class="field">
                    <label for="fecha">Fecha</label>
                    <input id="fecha" name="fecha" type="date" value="{{ $fecha }}">
                </div>
                <div class="field">
                    <label for="estado">Estado</label>
                    <select id="estado" name="estado">
                        <option value="">Todos</option>
                        @foreach (['Programada', 'Confirmada', 'Atendida', 'Cancelada', 'Inasistencia'] as $option)
                            <option value="{{ $option }}" @selected($estado === $option)>{{ $option }}</option>
                        @endforeach
                    </select>
                </div>
                <button class="button button-inline" type="submit">Buscar</button>
            </form>
            <div class="table-wrap">
                <table class="data-table appointment-table">
                    <thead>
                        <tr>
                            <th>Código</th>
                            <th>Paciente</th>
                            <th>Médico / especialidad</th>
                            <th>Fecha y hora</th>
                            <th>Estado</th>
                            <th>Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($appointments as $appointment)
                            @php
                                $start = \Illuminate\Support\Carbon::parse((string) $appointment->inicio, 'UTC');
                                $code = (string) $appointment->codigo;
                                $shortCode = strlen($code) > 10 ? 'CIT-…'.substr($code, -5) : $code;
                                $statusClass = match ((string) $appointment->estado) {
                                    'Programada' => 'is-programada',
                                    'Confirmada' => 'is-confirmada',
                                    'Atendida' => 'is-atendida',
                                    'Cancelada', 'Inasistencia' => 'is-inactive',
                                    default => '',
                                };
                                $canOpen = $has($P::BOOK_APPOINTMENTS) || $has($P::MARK_ATTENDED);
                                $canReschedule = in_array((string) $appointment->estado, ['Programada', 'Confirmada'], true)
                                    && $has($P::RESCHEDULE_APPOINTMENTS);
                                $canCancel = in_array((string) $appointment->estado, ['Programada', 'Confirmada'], true)
                                    && $has($P::CANCEL_APPOINTMENTS);
                                $canAttendance = $has($P::RECORD_ATTENDANCE);
                                $canAttend = in_array((string) $appointment->estado, ['Programada', 'Confirmada'], true)
                                    && $has($P::MARK_ATTENDED);
                                $hasMenu = $canCancel || $canAttendance || $canAttend;
                            @endphp
                            <tr>
                                <td>
                                    @if ($canOpen)
                                        <a class="code-link" href="{{ route('appointments.show', $appointment->id_cita) }}" title="{{ $code }}">{{ $shortCode }}</a>
                                    @else
                                        <span class="code-link" title="{{ $code }}">{{ $shortCode }}</span>
                                    @endif
                                </td>
                                <td>{{ $appointment->paciente_apellidos }}, {{ $appointment->paciente_nombres }}</td>
                                <td class="staff-cell">
                                    <span class="staff-line">
                                        <strong>{{ $appointment->medico_apellidos }}, {{ $appointment->medico_nombres }}</strong>
                                        <span class="staff-specialty">{{ $appointment->especialidad_nombre }}</span>
                                    </span>
                                </td>
                                <td class="when-cell">
                                    <span class="when-line">
                                        <strong>{{ $start->day }} {{ $months[(int) $start->month] }} {{ $start->year }}</strong>
                                        <span>{{ $start->format('H:i') }}</span>
                                    </span>
                                </td>
                                <td>
                                    <span class="status-pill {{ $statusClass }}">{{ $appointment->estado }}</span>
                                </td>
                                <td>
                                    <nav class="row-actions" aria-label="Acciones de {{ $appointment->codigo }}">
                                        @if ($canOpen)
                                            <a class="row-action" href="{{ route('appointments.show', $appointment->id_cita) }}">Ver</a>
                                        @endif
                                        @if ($canReschedule)
                                            <a class="row-action" href="{{ route('appointments.reschedule', $appointment->id_cita) }}">Reprogramar</a>
                                        @endif
                                        @if ($hasMenu)
                                            <details class="row-menu">
                                                <summary aria-label="Más acciones de {{ $appointment->codigo }}">⋯</summary>
                                                <div class="row-menu-list">
                                                    @if ($canAttendance)
                                                        <a href="{{ route('appointments.attendance', $appointment->id_cita) }}">Registrar asistencia</a>
                                                    @endif
                                                    @if ($canAttend)
                                                        <a href="{{ route('appointments.attend', $appointment->id_cita) }}">Marcar atendida</a>
                                                    @endif
                                                    @if ($canCancel)
                                                        <a class="is-danger" href="{{ route('appointments.cancel', $appointment->id_cita) }}">Cancelar cita</a>
                                                    @endif
                                                </div>
                                            </details>
                                        @endif
                                    </nav>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6">No hay citas que coincidan con la búsqueda.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            <div class="list-footer">
                <p class="list-meta">{{ $shown === 1 ? 'Mostrando 1 cita' : 'Mostrando '.$shown.' citas' }}</p>
                <p class="list-hint">Reprogramar conserva el código de la cita. Cancelar libera el horario.</p>
            </div>
        </div>
    </section>
@endsection
