@extends('layouts.clinic')

@section('title', 'Registrar especialidad — Clínica Vida Salud')

@section('content')
    <section class="page-head" aria-labelledby="page-title">
        <p class="eyebrow">Personal médico</p>
        <h1 id="page-title">Registrar especialidad</h1>
        <p class="lead">Espacios extra se compactan. “Pediatría” y “pediatría” se consideran el mismo nombre.</p>
    </section>

    @if ($errors->any())
        <div class="form-errors" role="alert"><p>{{ $errors->first() }}</p></div>
    @endif

    <form class="page-form" method="post" action="{{ route('specialties.store', absolute: false) }}" novalidate>
        @csrf
        <div class="field">
            <label for="nombre">Nombre</label>
            <input id="nombre" name="nombre" type="text" required maxlength="100" value="{{ old('nombre') }}">
        </div>
        <div class="field">
            <label for="descripcion">Descripción (opcional)</label>
            <input id="descripcion" name="descripcion" type="text" value="{{ old('descripcion') }}">
        </div>
        <div class="field">
            <label for="activo">Estado</label>
            <select id="activo" name="activo">
                <option value="1" @selected(old('activo', '1') === '1')>Activa</option>
                <option value="0" @selected(old('activo') === '0')>Inactiva</option>
            </select>
        </div>
        <button class="button" type="submit">Confirmar registro</button>
        <p><a href="{{ route('specialties.index') }}">Cancelar</a></p>
    </form>
@endsection
