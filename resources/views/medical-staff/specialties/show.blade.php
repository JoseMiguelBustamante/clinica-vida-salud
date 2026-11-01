@extends('layouts.clinic')

@section('title', 'Especialidad — Clínica Vida Salud')

@section('content')
    <section class="app-page" aria-labelledby="page-title">
        <p class="app-breadcrumb">Atención / Especialidades</p>
        <div class="page-toolbar">
            <div>
                <h1 id="page-title">{{ $specialty->nombre }}</h1>
                <p class="lead">
                    <span class="status-pill {{ $specialty->activo ? 'is-active' : 'is-inactive' }}">
                        {{ $specialty->activo ? 'Activa' : 'Inactiva' }}
                    </span>
                </p>
            </div>
            <nav class="row-actions" aria-label="Acciones">
                <a class="row-action" href="{{ route('specialties.edit', $specialty->id_especialidad) }}">Modificar</a>
                <a class="row-action" href="{{ route('specialties.index') }}">Volver al listado</a>
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
                    <dt>Descripción</dt>
                    <dd>{{ $specialty->descripcion ?: '—' }}</dd>
                </div>
            </dl>
        </article>

        @if ($specialty->activo)
            <form class="status-form" method="post" action="{{ route('specialties.deactivate', $specialty->id_especialidad, false) }}">
                @csrf
                <button class="button button-inline" type="submit">Desactivar</button>
            </form>
        @else
            <form class="status-form" method="post" action="{{ route('specialties.activate', $specialty->id_especialidad, false) }}">
                @csrf
                <button class="button button-inline" type="submit">Activar</button>
            </form>
        @endif
    </section>
@endsection
