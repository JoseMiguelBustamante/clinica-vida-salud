@extends('layouts.clinic')

@section('title', 'Citas — Clínica Vida Salud')

@php
    $shown = $appointments->count();
    $P = \App\Modules\Identity\PermissionCatalog::class;
    $codes = $identityPermissions ?? [];
    $has = static fn (string $code): bool => in_array($code, $codes, true);
@endphp

@section('content')
    <section class="app-page" aria-labelledby="page-title">
        <p class="app-breadcrumb">Atención / Citas</p>
        <div class="page-toolbar">
            <div>
                <h1 id="page-title">Citas registradas</h1>
                <p class="lead">Busque por código, paciente o médico. Reprogramar conserva el identificador; cancelar libera el horario.</p>
            </div>
            @if ($has($P::BOOK_APPOINTMENTS))
                <a class="button button-inline" href="{{ route('appointments.create') }}">Programar cita</a>
            @endif
        </div>

        @if (session('status'))
            <p class="form-status" role="status">{{ session('status') }}</p>
        @endif
        @if ($errors->any())
            <div class="form-errors" role="alert"><p>{{ $errors->first() }}</p></div>
        @endif

        <div class="list-card">
            <form class="filter-bar" method="get" action="{{ route('appointments.index', absolute: false) }}">
                <div class="field">
                    <label for="q">Buscar por código, paciente o médico</label>
                    <input id="q" name="q" type="search" value="{{ $q }}" placeholder="Código, paciente o médico">
                </div>
                <button class="button" type="submit">Buscar</button>
            </form>
            <div class="table-wrap">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Código</th>
                            <th>Paciente</th>
                            <th>Médico</th>
                            <th>Especialidad</th>
                            <th>Inicio</th>
                            <th>Estado</th>
                            <th>Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($appointments as $appointment)
                            <tr>
                                <td>{{ $appointment->codigo }}</td>
                                <td>{{ $appointment->paciente_apellidos }}, {{ $appointment->paciente_nombres }}</td>
                                <td>{{ $appointment->medico_apellidos }}, {{ $appointment->medico_nombres }}</td>
                                <td>{{ $appointment->especialidad_nombre }}</td>
                                <td>{{ \Illuminate\Support\Carbon::parse((string) $appointment->inicio)->timezone('UTC')->format('Y-m-d H:i') }}</td>
                                <td>
                                    <span class="status-pill {{ in_array($appointment->estado, ['Atendida', 'Confirmada'], true) ? 'is-active' : (in_array($appointment->estado, ['Cancelada', 'Inasistencia'], true) ? 'is-inactive' : '') }}">
                                        {{ $appointment->estado }}
                                    </span>
                                </td>
                                <td>
                                    <nav class="row-actions" aria-label="Acciones de {{ $appointment->codigo }}">
                                        @if ($has($P::BOOK_APPOINTMENTS))
                                            <a class="row-action" href="{{ route('appointments.show', $appointment->id_cita) }}">Ver</a>
                                        @endif
                                        @if (in_array($appointment->estado, ['Programada', 'Confirmada'], true) && $has($P::RESCHEDULE_APPOINTMENTS))
                                            <a class="row-action" href="{{ route('appointments.reschedule', $appointment->id_cita) }}">Reprogramar</a>
                                        @endif
                                        @if (in_array($appointment->estado, ['Programada', 'Confirmada'], true) && $has($P::CANCEL_APPOINTMENTS))
                                            <a class="row-action" href="{{ route('appointments.cancel', $appointment->id_cita) }}">Cancelar</a>
                                        @endif
                                        @if ($has($P::RECORD_ATTENDANCE))
                                            <a class="row-action" href="{{ route('appointments.attendance', $appointment->id_cita) }}">Asistencia</a>
                                        @endif
                                        @if (in_array($appointment->estado, ['Programada', 'Confirmada'], true) && $has($P::MARK_ATTENDED))
                                            <a class="row-action" href="{{ route('appointments.attend', $appointment->id_cita) }}">Atender</a>
                                        @endif
                                    </nav>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="7">No hay citas que coincidan con la búsqueda.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            <p class="list-meta">{{ $shown === 1 ? 'Mostrando 1 cita' : 'Mostrando '.$shown.' citas' }}</p>
        </div>
    </section>
@endsection
