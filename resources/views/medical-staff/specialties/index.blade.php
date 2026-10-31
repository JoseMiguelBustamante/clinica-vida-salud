@extends('layouts.clinic')

@section('title', 'Especialidades — Clínica Vida Salud')

@section('content')
    <section class="page-head" aria-labelledby="page-title">
        <p class="eyebrow">Personal médico</p>
        <h1 id="page-title">Especialidades</h1>
        <p class="lead">Catálogo clínico. El nombre se normaliza y no puede repetirse. Desactivar no borra médicos ni citas.</p>
        <p><a class="button button-inline" href="{{ route('specialties.create') }}">Registrar especialidad</a></p>
    </section>

    @if (session('status'))
        <p class="form-errors" role="status">{{ session('status') }}</p>
    @endif
    @if ($errors->any())
        <div class="form-errors" role="alert"><p>{{ $errors->first() }}</p></div>
    @endif

    <form class="search-bar" method="get" action="{{ route('specialties.index', absolute: false) }}">
        <div class="field">
            <label for="q">Buscar por nombre</label>
            <input id="q" name="q" type="search" value="{{ $q }}">
        </div>
        <button class="button button-inline" type="submit">Buscar</button>
    </form>

    <div class="table-wrap">
        <table class="data-table">
            <thead>
                <tr>
                    <th>Nombre</th>
                    <th>Descripción</th>
                    <th>Estado</th>
                    <th>Acciones</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($specialties as $specialty)
                    <tr>
                        <td>{{ $specialty->nombre }}</td>
                        <td>{{ $specialty->descripcion ?: '—' }}</td>
                        <td>{{ $specialty->activo ? 'Activa' : 'Inactiva' }}</td>
                        <td>
                            <a href="{{ route('specialties.show', $specialty->id_especialidad) }}">Ver</a>
                            ·
                            <a href="{{ route('specialties.edit', $specialty->id_especialidad) }}">Modificar</a>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="4">No hay especialidades que coincidan con la búsqueda.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
@endsection
