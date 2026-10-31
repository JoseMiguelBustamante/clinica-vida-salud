@extends('layouts.clinic')

@section('title', 'Cuenta — Clínica Vida Salud')

@section('content')
    <section class="page-head" aria-labelledby="page-title">
        <p class="eyebrow">Administración</p>
        <h1 id="page-title">{{ $account->nombre_usuario }}</h1>
        <p class="lead">{{ $account->rol_nombre }} · {{ $account->activo ? 'Activa' : 'Inactiva' }}</p>
    </section>

    @if (session('status'))
        <p class="form-errors" role="status">{{ session('status') }}</p>
    @endif
    @if ($errors->any())
        <div class="form-errors" role="alert"><p>{{ $errors->first() }}</p></div>
    @endif

    <section class="implementation">
        <p>Correo de acceso: {{ $account->correo_acceso ?: '—' }}</p>
        <p><a href="{{ route('accounts.edit', $account->id_usuario) }}">Modificar</a>
            · <a href="{{ route('accounts.index') }}">Volver al listado</a></p>
        @if ($account->activo)
            <form method="post" action="{{ route('accounts.deactivate', $account->id_usuario, false) }}">
                @csrf
                <button class="button button-inline" type="submit">Desactivar</button>
            </form>
        @else
            <form method="post" action="{{ route('accounts.activate', $account->id_usuario, false) }}">
                @csrf
                <button class="button button-inline" type="submit">Activar</button>
            </form>
        @endif
    </section>
@endsection
