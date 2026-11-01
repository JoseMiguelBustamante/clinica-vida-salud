@extends('layouts.clinic')

@section('title', 'Registrar usuario — Clínica Vida Salud')

@section('content')
    <section class="app-page" aria-labelledby="page-title">
        <p class="app-breadcrumb">Administración / Usuarios</p>
        <h1 id="page-title">Registrar usuario</h1>
        <p class="lead">El nombre de usuario y el correo no se recortan. La contraseña se almacena como hash.</p>

        @if ($errors->any())
            <div class="form-errors" role="alert"><p>{{ $errors->first() }}</p></div>
        @endif

        <form class="page-form" method="post" action="{{ route('accounts.store', absolute: false) }}" novalidate>
            @csrf
            <div class="field">
                <label for="nombre_usuario">Nombre de usuario</label>
                <input id="nombre_usuario" name="nombre_usuario" type="text" required maxlength="100"
                    value="{{ old('nombre_usuario') }}" autocomplete="off">
            </div>
            <div class="field">
                <label for="correo_acceso">Correo de acceso (opcional)</label>
                <input id="correo_acceso" name="correo_acceso" type="text" maxlength="255"
                    value="{{ old('correo_acceso') }}" autocomplete="off">
            </div>
            <div class="field">
                <label for="password">Contraseña inicial</label>
                <input id="password" name="password" type="password" required minlength="12" autocomplete="new-password">
            </div>
            <div class="field">
                <label for="id_rol">Rol</label>
                <select id="id_rol" name="id_rol" required>
                    <option value="">Seleccione un rol</option>
                    @foreach ($roles as $role)
                        <option value="{{ $role->id_rol }}" @selected((string) old('id_rol') === (string) $role->id_rol)>
                            {{ $role->nombre }}
                        </option>
                    @endforeach
                </select>
            </div>
            <div class="field">
                <label for="activo">Estado</label>
                <select id="activo" name="activo">
                    <option value="1" @selected(old('activo', '1') === '1')>Activo</option>
                    <option value="0" @selected(old('activo') === '0')>Inactivo</option>
                </select>
            </div>
            <button class="button" type="submit">Confirmar registro</button>
            <p><a href="{{ route('accounts.index') }}">Cancelar</a></p>
        </form>
    </section>
@endsection
