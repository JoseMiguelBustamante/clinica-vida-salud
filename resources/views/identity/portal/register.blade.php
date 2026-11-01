@extends('layouts.clinic')

@section('title', 'Registrar cuenta — Clínica Vida Salud')

@section('content')
    <section class="page-split" aria-labelledby="page-title">
        <div class="page-copy">
            <p class="eyebrow">Portal del paciente</p>
            <h1 id="page-title">Registrar cuenta</h1>
            <p class="lead">Cree un usuario y una contraseña para el portal. Esta cuenta no queda vinculada a un registro de paciente ni concede acceso a citas.</p>
        </div>

        <div class="page-form">
            @if (session('status'))
                <p class="form-errors" role="status">{{ session('status') }}</p>
            @endif
            @if ($errors->any())
                <div class="form-errors" role="alert"><p>{{ $errors->first() }}</p></div>
            @endif

            @if ($enabled)
                <form class="auth-form" method="post" action="{{ route('portal.register.store', absolute: false) }}" novalidate>
                    @csrf
                    <div class="field">
                        <label for="nombre_usuario">Nombre de usuario</label>
                        <input id="nombre_usuario" name="nombre_usuario" type="text" required maxlength="100"
                            value="{{ old('nombre_usuario') }}" autocomplete="username">
                    </div>
                    <div class="field">
                        <label for="correo_acceso">Correo de acceso (opcional)</label>
                        <input id="correo_acceso" name="correo_acceso" type="text" maxlength="255"
                            value="{{ old('correo_acceso') }}" autocomplete="email">
                    </div>
                    <div class="field">
                        <label for="password">Contraseña</label>
                        <input id="password" name="password" type="password" required minlength="12" autocomplete="new-password">
                    </div>
                    <div class="field">
                        <label for="password_confirmation">Confirmar contraseña</label>
                        <input id="password_confirmation" name="password_confirmation" type="password" required minlength="12" autocomplete="new-password">
                    </div>
                    <button class="button" type="submit">Crear cuenta</button>
                </form>
                <p><a href="{{ route('login') }}">Ya tengo una cuenta</a>
                    · <a href="{{ route('login') }}">Cancelar</a></p>
            @else
                <p>El registro de cuentas no está habilitado.</p>
                <p><a href="{{ route('login') }}">Iniciar sesión</a></p>
            @endif
        </div>
    </section>
@endsection
