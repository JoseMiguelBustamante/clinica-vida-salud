@extends('layouts.clinic')

@section('title', 'Médico — Clínica Vida Salud')

@section('content')
    <section class="page-head" aria-labelledby="page-title">
        <p class="eyebrow">Personal médico</p>
        <h1 id="page-title">{{ $physician->nombres }} {{ $physician->apellidos }}</h1>
        <p class="lead">{{ $physician->activo ? 'Activo' : 'Inactivo' }}
            · Registro {{ $physician->registro_profesional }}</p>
    </section>

    @if (session('status'))
        <p class="form-errors" role="status">{{ session('status') }}</p>
    @endif
    @if ($errors->any())
        <div class="form-errors" role="alert"><p>{{ $errors->first() }}</p></div>
    @endif

    <section class="implementation">
        <p>Teléfono: {{ $physician->telefono ?: '—' }}</p>
        <p>Correo de contacto: {{ $physician->correo_contacto ?: '—' }}</p>
        <p>Cuenta de acceso: {{ $physician->id_usuario ? 'vinculada' : 'sin vincular' }}</p>
        <p>Especialidades: {{ $physician->especialidades->pluck('nombre')->join(', ') ?: '—' }}</p>
        <p><a href="{{ route('physicians.edit', $physician->id_medico) }}">Modificar</a>
            · <a href="{{ route('physicians.index') }}">Volver al listado</a></p>
        @if ($physician->activo)
            <form method="post" action="{{ route('physicians.deactivate', $physician->id_medico, false) }}">
                @csrf
                <button class="button button-inline" type="submit">Desactivar</button>
            </form>
        @else
            <form method="post" action="{{ route('physicians.activate', $physician->id_medico, false) }}">
                @csrf
                <button class="button button-inline" type="submit">Activar</button>
            </form>
        @endif
    </section>
@endsection
