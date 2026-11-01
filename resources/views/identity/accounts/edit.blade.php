@extends('layouts.clinic')

@section('title', 'Modificar usuario — Clínica Vida Salud')

@section('content')
    <section class="app-page" aria-labelledby="page-title">
        <p class="app-breadcrumb">Administración / Usuarios</p>
        <h1 id="page-title">Modificar usuario</h1>
        <p class="lead">Si no indica una contraseña nueva, se conserva la actual.</p>

        @if ($errors->any())
            <div class="form-errors" role="alert"><p>{{ $errors->first() }}</p></div>
        @endif

        <form class="page-form" method="post" action="{{ route('accounts.update', $account->id_usuario, false) }}" novalidate>
            @csrf
            <div class="field">
                <label for="nombre_usuario">Nombre de usuario</label>
                <input id="nombre_usuario" name="nombre_usuario" type="text" required maxlength="100"
                    value="{{ old('nombre_usuario', $account->nombre_usuario) }}" autocomplete="off">
            </div>
            <div class="field">
                <label for="correo_acceso">Correo de acceso (opcional)</label>
                <input id="correo_acceso" name="correo_acceso" type="text" maxlength="255"
                    value="{{ old('correo_acceso', $account->correo_acceso) }}" autocomplete="off">
            </div>
            <div class="field">
                <label for="password">Nueva contraseña (opcional)</label>
                <input id="password" name="password" type="password" minlength="12" autocomplete="new-password">
            </div>
            <div class="field">
                <label for="id_rol">Rol</label>
                <select id="id_rol" name="id_rol" required>
                    @foreach ($roles as $role)
                        <option value="{{ $role->id_rol }}"
                            @selected((string) old('id_rol', $account->id_rol) === (string) $role->id_rol)>
                            {{ $role->nombre }}
                        </option>
                    @endforeach
                </select>
            </div>
            <div class="field">
                <label for="activo">Estado</label>
                <select id="activo" name="activo">
                    <option value="1" @selected((string) old('activo', $account->activo ? '1' : '0') === '1')>Activo</option>
                    <option value="0" @selected((string) old('activo', $account->activo ? '1' : '0') === '0')>Inactivo</option>
                </select>
            </div>
            <button class="button" type="submit">Guardar cambios</button>
            <p><a href="{{ route('accounts.show', $account->id_usuario) }}">Cancelar</a></p>
        </form>
    </section>
@endsection
