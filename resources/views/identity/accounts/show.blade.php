@extends('layouts.clinic')

@section('title', 'Cuenta — Clínica Vida Salud')

@section('content')
    <section class="app-page" aria-labelledby="page-title">
        <p class="app-breadcrumb">Administración / Usuarios</p>
        <div class="page-toolbar">
            <div>
                <h1 id="page-title">{{ $account->nombre_usuario }}</h1>
                <p class="lead">
                    {{ $account->rol_nombre }}
                    ·
                    <span class="status-pill {{ $account->activo ? 'is-active' : 'is-inactive' }}">
                        {{ $account->activo ? 'Activa' : 'Inactiva' }}
                    </span>
                </p>
            </div>
            <nav class="row-actions" aria-label="Acciones">
                <a class="row-action" href="{{ route('accounts.edit', $account->id_usuario) }}">Modificar</a>
                <a class="row-action" href="{{ route('accounts.index') }}">Volver al listado</a>
            </nav>
        </div>

        @if (session('status'))
            <p class="form-status" role="status">{{ session('status') }}</p>
        @endif
        @if ($errors->any())
            <div class="form-errors" role="alert"><p>{{ $errors->first() }}</p></div>
        @endif

        <article class="list-card detail-card">
            <dl class="detail-list">
                <div>
                    <dt>Correo de acceso</dt>
                    <dd>{{ $account->correo_acceso ?: '—' }}</dd>
                </div>
            </dl>
        </article>

        @if ($account->activo)
            <form class="status-form" method="post" action="{{ route('accounts.deactivate', $account->id_usuario, false) }}">
                @csrf
                <button class="button button-inline" type="submit">Desactivar</button>
            </form>
        @else
            <form class="status-form" method="post" action="{{ route('accounts.activate', $account->id_usuario, false) }}">
                @csrf
                <button class="button button-inline" type="submit">Activar</button>
            </form>
        @endif
    </section>
@endsection
