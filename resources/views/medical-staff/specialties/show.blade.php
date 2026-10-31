@extends('layouts.clinic')

@section('title', 'Especialidad — Clínica Vida Salud')

@section('content')
    <section class="page-head" aria-labelledby="page-title">
        <p class="eyebrow">Personal médico</p>
        <h1 id="page-title">{{ $specialty->nombre }}</h1>
        <p class="lead">{{ $specialty->activo ? 'Activa' : 'Inactiva' }}</p>
    </section>

    @if (session('status'))
        <p class="form-errors" role="status">{{ session('status') }}</p>
    @endif
    @if ($errors->any())
        <div class="form-errors" role="alert"><p>{{ $errors->first() }}</p></div>
    @endif

    <section class="implementation">
        <p>Descripción: {{ $specialty->descripcion ?: '—' }}</p>
        <p><a href="{{ route('specialties.edit', $specialty->id_especialidad) }}">Modificar</a>
            · <a href="{{ route('specialties.index') }}">Volver al listado</a></p>
        @if ($specialty->activo)
            <form method="post" action="{{ route('specialties.deactivate', $specialty->id_especialidad, false) }}">
                @csrf
                <button class="button button-inline" type="submit">Desactivar</button>
            </form>
        @else
            <form method="post" action="{{ route('specialties.activate', $specialty->id_especialidad, false) }}">
                @csrf
                <button class="button button-inline" type="submit">Activar</button>
            </form>
        @endif
    </section>
@endsection
