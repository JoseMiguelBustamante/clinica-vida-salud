@extends('layouts.clinic')

@section('title', 'Usuarios y roles — Clínica Vida Salud')

@section('content')
    <section class="page-head" aria-labelledby="page-title">
        <p class="eyebrow">Administración</p>
        <h1 id="page-title">Usuarios y roles</h1>
        <p class="lead">Cuentas de acceso del personal. Una cuenta inactiva no puede iniciar sesión.</p>
        <p><a class="button button-inline" href="{{ route('accounts.create') }}">Registrar usuario</a></p>
    </section>

    @if (session('status'))
        <p class="form-errors" role="status">{{ session('status') }}</p>
    @endif
    @if ($errors->any())
        <div class="form-errors" role="alert"><p>{{ $errors->first() }}</p></div>
    @endif

    <form class="search-bar" method="get" action="{{ route('accounts.index', absolute: false) }}">
        <div class="field">
            <label for="q">Buscar por usuario o correo</label>
            <input id="q" name="q" type="search" value="{{ $q }}">
        </div>
        <button class="button button-inline" type="submit">Buscar</button>
    </form>

    <div class="table-wrap">
        <table class="data-table">
            <thead>
                <tr>
                    <th>Usuario</th>
                    <th>Correo</th>
                    <th>Rol</th>
                    <th>Estado</th>
                    <th>Acciones</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($accounts as $account)
                    <tr>
                        <td>{{ $account->nombre_usuario }}</td>
                        <td>{{ $account->correo_acceso ?: '—' }}</td>
                        <td>{{ $account->rol_nombre }}</td>
                        <td>{{ $account->activo ? 'Activo' : 'Inactivo' }}</td>
                        <td>
                            <a href="{{ route('accounts.show', $account->id_usuario) }}">Ver</a>
                            ·
                            <a href="{{ route('accounts.edit', $account->id_usuario) }}">Modificar</a>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="5">No hay cuentas que coincidan con la búsqueda.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
@endsection
