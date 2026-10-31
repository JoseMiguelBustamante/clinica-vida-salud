@extends('layouts.clinic')

@section('title', 'Médicos — Clínica Vida Salud')

@section('content')
    <section class="page-head" aria-labelledby="page-title">
        <p class="eyebrow">Personal médico</p>
        <h1 id="page-title">Médicos</h1>
        <p class="lead">Registro profesional único y al menos una especialidad. Un médico inactivo no recibe nuevas citas.</p>
        <p><a class="button button-inline" href="{{ route('physicians.create') }}">Registrar médico</a></p>
    </section>

    @if (session('status'))
        <p class="form-errors" role="status">{{ session('status') }}</p>
    @endif
    @if ($errors->any())
        <div class="form-errors" role="alert"><p>{{ $errors->first() }}</p></div>
    @endif

    <form class="search-bar" method="get" action="{{ route('physicians.index', absolute: false) }}">
        <div class="field">
            <label for="q">Buscar por nombre, registro o especialidad</label>
            <input id="q" name="q" type="search" value="{{ $q }}">
        </div>
        <button class="button button-inline" type="submit">Buscar</button>
    </form>

    <div class="table-wrap">
        <table class="data-table">
            <thead>
                <tr>
                    <th>Registro</th>
                    <th>Nombre</th>
                    <th>Especialidades</th>
                    <th>Estado</th>
                    <th>Acciones</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($physicians as $physician)
                    <tr>
                        <td>{{ $physician->registro_profesional }}</td>
                        <td>{{ $physician->nombres }} {{ $physician->apellidos }}</td>
                        <td>
                            {{ $physician->especialidades->pluck('nombre')->join(', ') ?: '—' }}
                        </td>
                        <td>{{ $physician->activo ? 'Activo' : 'Inactivo' }}</td>
                        <td>
                            <a href="{{ route('physicians.show', $physician->id_medico) }}">Ver</a>
                            ·
                            <a href="{{ route('physicians.edit', $physician->id_medico) }}">Modificar</a>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="5">No hay médicos que coincidan con la búsqueda.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
@endsection
