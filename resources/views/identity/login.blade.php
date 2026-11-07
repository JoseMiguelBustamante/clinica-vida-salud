@extends('layouts.clinic')

@section('title', 'Iniciar sesión — Clínica Vida Salud')
@section('body_class', 'is-login')
@section('main_class', 'login-main')
@section('hide_chrome', '1')

@section('content')
    <section class="login-screen" aria-labelledby="page-title">
        <div class="login-brand">
            <div class="login-brand-copy">
                <a class="login-logo" href="{{ route('home') }}">
                    <span class="brand-badge" aria-hidden="true">+</span>
                    <span>Clínica Vida Salud</span>
                </a>
                <p class="login-kicker">Gestión de citas médicas</p>
                <p class="lead">Acceso del personal para coordinar pacientes, horarios y citas.</p>
            </div>
            <img
                class="login-brand-art"
                src="/images/login-medical-props.jpg"
                alt=""
                width="960"
                height="540"
                decoding="async"
                aria-hidden="true"
            >
        </div>

        <div class="login-panel">
            <div class="login-card">
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
                        <div class="login-input">
                            <span class="login-input-icon" aria-hidden="true">
                                <svg viewBox="0 0 24 24" focusable="false">
                                    <path fill="currentColor" d="M4 6.5A2.5 2.5 0 0 1 6.5 4h11A2.5 2.5 0 0 1 20 6.5v11a2.5 2.5 0 0 1-2.5 2.5h-11A2.5 2.5 0 0 1 4 17.5v-11Zm2.5-.5a.5.5 0 0 0-.5.5v.3l6 3.9 6-3.9V6.5a.5.5 0 0 0-.5-.5h-11Zm11.5 3.1-5.7 3.7a1.2 1.2 0 0 1-1.3 0L5.3 9.1V17.5a.5.5 0 0 0 .5.5h11a.5.5 0 0 0 .5-.5V9.1Z"/>
                                </svg>
                            </span>
                            <input id="identifier" name="identifier" type="text" value="{{ old('identifier') }}"
                                autocomplete="username" required maxlength="255"
                                placeholder="usuario o correo">
                        </div>
                    </div>
                    <div class="field">
                        <label for="password">Contraseña</label>
                        <div class="login-input">
                            <span class="login-input-icon" aria-hidden="true">
                                <svg viewBox="0 0 24 24" focusable="false">
                                    <path fill="currentColor" d="M8.5 10V7.5a3.5 3.5 0 0 1 7 0V10H17a2 2 0 0 1 2 2v7a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2v-7a2 2 0 0 1 2-2h1.5Zm2 0h3V7.5a1.5 1.5 0 0 0-3 0V10Z"/>
                                </svg>
                            </span>
                            <input id="password" name="password" type="password" autocomplete="current-password" required
                                placeholder="••••••••">
                            <span class="login-input-trail" aria-hidden="true" title="La contraseña permanece oculta">
                                <svg viewBox="0 0 24 24" focusable="false">
                                    <path fill="currentColor" d="M12 5c5.2 0 9.3 4.1 10 7-.7 2.9-4.8 7-10 7S2.7 14.9 2 12c.7-2.9 4.8-7 10-7Zm0 2c-3.7 0-6.8 2.7-7.8 5 1 2.3 4.1 5 7.8 5s6.8-2.7 7.8-5c-1-2.3-4.1-5-7.8-5Zm0 1.8A3.2 3.2 0 1 1 8.8 12 3.2 3.2 0 0 1 12 8.8Zm0 1.7A1.5 1.5 0 1 0 13.5 12 1.5 1.5 0 0 0 12 10.5Z"/>
                                </svg>
                            </span>
                        </div>
                    </div>
                    <button class="button" type="submit">Entrar</button>
                </form>
                <p class="login-alt"><a href="{{ route('portal.register') }}">Crear cuenta de paciente</a></p>
            </div>
        </div>
    </section>
@endsection
