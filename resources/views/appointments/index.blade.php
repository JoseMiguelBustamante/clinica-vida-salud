@extends('layouts.clinic')

@section('title', 'Citas — Clínica Vida Salud')

@section('content')
    <section class="page-head" aria-labelledby="page-title">
        <p class="eyebrow">Citas</p>
        <h1 id="page-title">Citas registradas</h1>
        <p class="lead">Busque por código, paciente o médico. Reprogramar conserva el identificador; cancelar libera el horario.</p>
        <p>
            @if (in_array(\App\Modules\Identity\PermissionCatalog::BOOK_APPOINTMENTS, $identityPermissions ?? [], true))
                <a class="button button-inline" href="{{ route('appointments.create') }}">Programar cita</a>
            @endif
            @if (in_array(\App\Modules\Identity\PermissionCatalog::CONSULT_AVAILABILITY, $identityPermissions ?? [], true))
                · <a href="{{ route('availability.consult') }}">Consultar disponibilidad</a>
            @endif
        </p>
    </section>

    @if (session('status'))
        <p class="form-errors" role="status">{{ session('status') }}</p>
    @endif
    @if ($errors->any())
        <div class="form-errors" role="alert"><p>{{ $errors->first() }}</p></div>
    @endif

    <form class="search-bar" method="get" action="{{ route('appointments.index', absolute: false) }}">
        <div class="field">
            <label for="q">Buscar por código, paciente o médico</label>
            <input id="q" name="q" type="search" value="{{ $q }}">
        </div>
        <button class="button button-inline" type="submit">Buscar</button>
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
                        <td>{{ $appointment->estado }}</td>
                        <td>
                            @if (in_array(\App\Modules\Identity\PermissionCatalog::BOOK_APPOINTMENTS, $identityPermissions ?? [], true))
                                <a href="{{ route('appointments.show', $appointment->id_cita) }}">Ver</a>
                            @endif
                            @if (in_array($appointment->estado, ['Programada', 'Confirmada'], true)
                                && in_array(\App\Modules\Identity\PermissionCatalog::RESCHEDULE_APPOINTMENTS, $identityPermissions ?? [], true))
                                @if (in_array(\App\Modules\Identity\PermissionCatalog::BOOK_APPOINTMENTS, $identityPermissions ?? [], true))
                                    ·
                                @endif
                                <a href="{{ route('appointments.reschedule', $appointment->id_cita) }}">Reprogramar</a>
                            @endif
                            @if (in_array($appointment->estado, ['Programada', 'Confirmada'], true)
                                && in_array(\App\Modules\Identity\PermissionCatalog::CANCEL_APPOINTMENTS, $identityPermissions ?? [], true))
                                · <a href="{{ route('appointments.cancel', $appointment->id_cita) }}">Cancelar</a>
                            @endif
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
@endsection
