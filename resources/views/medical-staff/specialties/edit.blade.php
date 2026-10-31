@extends('layouts.clinic')

@section('title', 'Modificar especialidad — Clínica Vida Salud')

@section('content')
    <section class="page-head" aria-labelledby="page-title">
        <p class="eyebrow">Personal médico</p>
        <h1 id="page-title">Modificar especialidad</h1>
    </section>

    @if ($errors->any())
        <div class="form-errors" role="alert"><p>{{ $errors->first() }}</p></div>
    @endif

    <form class="page-form" method="post" action="{{ route('specialties.update', $specialty->id_especialidad, false) }}" novalidate>
        @csrf
        <div class="field">
            <label for="nombre">Nombre</label>
            <input id="nombre" name="nombre" type="text" required maxlength="100"
                value="{{ old('nombre', $specialty->nombre) }}">
        </div>
        <div class="field">
            <label for="descripcion">Descripción (opcional)</label>
            <input id="descripcion" name="descripcion" type="text"
                value="{{ old('descripcion', $specialty->descripcion) }}">
        </div>
        <button class="button" type="submit">Guardar cambios</button>
        <p><a href="{{ route('specialties.show', $specialty->id_especialidad) }}">Cancelar</a></p>
    </form>
@endsection
