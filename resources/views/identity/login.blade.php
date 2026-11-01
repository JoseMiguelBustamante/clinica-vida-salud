@extends('layouts.clinic')

@section('title', 'Iniciar sesión — Clínica Vida Salud')
@section('body_class', 'is-login')
@section('main_class', 'login-main')
@section('hide_chrome', '1')

@section('content')
    <section class="login-screen" aria-labelledby="page-title">
        <div class="login-brand">
            <a class="login-logo" href="{{ route('home') }}">
                <span class="brand-badge" aria-hidden="true">+</span>
                <span>Clínica Vida Salud</span>
            </a>
            <p class="login-kicker">Gestión de citas médicas</p>
            <p class="lead">Acceso del personal para coordinar pacientes, horarios y citas.</p>
        </div>

        <div class="login-panel">
            <h1 id="page-title">Iniciar sesión</h1>
            <p class="login-subtitle">El usuario o el correo y la contraseña no se recortan ni se cambian de mayúsculas.</p>

            @if (session('status'))
                <p class="form-errors" role="status">{{ session('status') }}</p>
            @endif
            @if ($errors->any())
                <div class="form-errors" role="alert">
                    <p>{{ $errors->first() }}</p>
                </div>
            @endif

            <form class="auth-form" method="post" action="{{ route('login.attempt', absolute: false) }}" novalidate>
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
            <p class="login-alt"><a href="{{ route('portal.register') }}">Crear cuenta de paciente</a></p>
        </div>
    </section>
@endsection
