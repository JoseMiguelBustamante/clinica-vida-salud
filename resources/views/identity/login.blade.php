@extends('layouts.clinic')

@section('title', 'Iniciar sesión — Clínica Vida Salud')
@section('body_class', 'is-auth')

@section('content')
    <section class="auth-panel" aria-labelledby="page-title">
        <div class="auth-card">
            <p class="eyebrow">Personal de la clínica</p>
            <h1 id="page-title">Iniciar sesión</h1>
            <p class="auth-lead">Use el usuario o el correo de acceso. Las credenciales no se recortan ni se cambian de mayúsculas.</p>

            @if ($errors->any())
                <div class="form-errors" role="alert">
                    <p>{{ $errors->first() }}</p>
                </div>
            @endif

            <form class="auth-form" method="post" action="{{ route('login.attempt') }}" novalidate>
                @csrf
                <div class="field">
                    <label for="identifier">Usuario o correo</label>
                    <input id="identifier" name="identifier" type="text" value="{{ old('identifier') }}"
                        autocomplete="username" required maxlength="255">
                </div>
                <div class="field">
                    <label for="password">Contraseña</label>
                    <input id="password" name="password" type="password" autocomplete="current-password" required>
                </div>
                <button class="button" type="submit">Entrar</button>
            </form>
        </div>
    </section>
@endsection
