@extends('layouts.clinic')

@section('title', 'Médico — Clínica Vida Salud')

@section('content')
    <section class="app-page" aria-labelledby="page-title">
        <p class="app-breadcrumb">Atención / Médicos</p>
        <div class="page-toolbar">
            <div>
                <h1 id="page-title">{{ $physician->nombres }} {{ $physician->apellidos }}</h1>
                <p class="lead">
                    <span class="status-pill {{ $physician->activo ? 'is-active' : 'is-inactive' }}">
                        {{ $physician->activo ? 'Activo' : 'Inactivo' }}
                    </span>
                    · Registro {{ $physician->registro_profesional }}
                </p>
            </div>
            <nav class="row-actions" aria-label="Acciones">
                <a class="row-action" href="{{ route('physicians.edit', $physician->id_medico) }}">Modificar</a>
                <a class="row-action" href="{{ route('physicians.index') }}">Volver al listado</a>
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
                    <dt>Teléfono</dt>
                    <dd>{{ $physician->telefono ?: '—' }}</dd>
                </div>
                <div>
                    <dt>Correo de contacto</dt>
                    <dd>{{ $physician->correo_contacto ?: '—' }}</dd>
                </div>
                <div>
                    <dt>Cuenta de acceso</dt>
                    <dd>{{ $physician->id_usuario ? 'vinculada' : 'sin vincular' }}</dd>
                </div>
                <div>
                    <dt>Especialidades</dt>
                    <dd>{{ $physician->especialidades->pluck('nombre')->join(', ') ?: '—' }}</dd>
                </div>
            </dl>
        </article>

        @if ($physician->activo)
            <form class="status-form" method="post" action="{{ route('physicians.deactivate', $physician->id_medico, false) }}">
                @csrf
                <button class="button button-inline" type="submit">Desactivar</button>
            </form>
        @else
            <form class="status-form" method="post" action="{{ route('physicians.activate', $physician->id_medico, false) }}">
                @csrf
                <button class="button button-inline" type="submit">Activar</button>
            </form>
        @endif
    </section>
@endsection
