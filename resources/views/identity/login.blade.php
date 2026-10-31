@extends('layouts.clinic')

@section('title', 'Iniciar sesión — Clínica Vida Salud')

@section('content')
    <section class="page-split" aria-labelledby="page-title">
        <div class="page-copy">
            <p class="eyebrow">Personal de la clínica</p>
            <h1 id="page-title">Iniciar sesión</h1>
            <p class="lead">Acceso del personal para coordinar pacientes, horarios y citas.
                El usuario o el correo y la contraseña no se recortan ni se cambian de mayúsculas.</p>
        </div>

        <div class="page-form">
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
        </div>
    </section>
@endsection
