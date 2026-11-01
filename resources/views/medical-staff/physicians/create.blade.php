@extends('layouts.clinic')

@section('title', 'Registrar médico — Clínica Vida Salud')

@section('content')
    <section class="app-page" aria-labelledby="page-title">
        <p class="app-breadcrumb">Atención / Médicos</p>
        <h1 id="page-title">Registrar médico</h1>
        <p class="lead">Debe asociar al menos una especialidad activa.</p>

        @if ($errors->any())
            <div class="form-errors" role="alert"><p>{{ $errors->first() }}</p></div>
        @endif

        <form class="page-form" method="post" action="{{ route('physicians.store', absolute: false) }}" novalidate>
            @csrf
            @include('medical-staff.physicians._form')
            <button class="button" type="submit">Confirmar registro</button>
            <p><a href="{{ route('physicians.index') }}">Cancelar</a></p>
        </form>
    </section>
@endsection
