@extends('layouts.clinic')

@section('title', 'Pacientes — Clínica Vida Salud')

@section('content')
    <section class="page-head" aria-labelledby="page-title">
        <p class="eyebrow">Gestión clínica</p>
        <h1 id="page-title">Pacientes</h1>
        <p class="lead">Identificación y contacto. El CI es opcional y, si se indica, no puede repetirse. Desactivar no borra citas.</p>
        <p><a class="button button-inline" href="{{ route('patients.create') }}">Registrar paciente</a></p>
    </section>

    @if (session('status'))
        <p class="form-errors" role="status">{{ session('status') }}</p>
    @endif
    @if ($errors->any())
        <div class="form-errors" role="alert"><p>{{ $errors->first() }}</p></div>
    @endif

    <form class="search-bar" method="get" action="{{ route('patients.index', absolute: false) }}">
        <div class="field">
            <label for="q">Buscar por CI, nombres o apellidos</label>
            <input id="q" name="q" type="search" value="{{ $q }}">
        </div>
        <button class="button button-inline" type="submit">Buscar</button>
    </form>

    <div class="table-wrap">
        <table class="data-table">
            <thead>
                <tr>
                    <th>CI</th>
                    <th>Nombres</th>
                    <th>Apellidos</th>
                    <th>Teléfono</th>
                    <th>Estado</th>
                    <th>Acciones</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($patients as $patient)
                    <tr>
                        <td>{{ $patient->ci ?: '—' }}</td>
                        <td>{{ $patient->nombres }}</td>
                        <td>{{ $patient->apellidos }}</td>
                        <td>{{ $patient->telefono ?: '—' }}</td>
                        <td>{{ $patient->activo ? 'Activo' : 'Inactivo' }}</td>
                        <td>
                            <a href="{{ route('patients.show', $patient->id_paciente) }}">Ver</a>
                            ·
                            <a href="{{ route('patients.edit', $patient->id_paciente) }}">Modificar</a>
                            @if (in_array(\App\Modules\Identity\PermissionCatalog::CONSULT_HISTORY, $identityPermissions ?? [], true))
                                ·
                                <a href="{{ route('appointments.history', ['id_paciente' => $patient->id_paciente]) }}">Historial</a>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="6">No hay pacientes que coincidan con la búsqueda.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
@endsection
