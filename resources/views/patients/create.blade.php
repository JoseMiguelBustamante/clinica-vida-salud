@extends('layouts.clinic')

@section('title', 'Registrar paciente — Clínica Vida Salud')

@section('content')
    <section class="page-head" aria-labelledby="page-title">
        <p class="eyebrow">Gestión clínica</p>
        <h1 id="page-title">Registrar paciente</h1>
        <p class="lead">El documento de identidad es opcional. Si lo deja vacío, se guarda como ausente y no choca con otros vacíos.</p>
    </section>

    @if ($errors->any())
        <div class="form-errors" role="alert"><p>{{ $errors->first() }}</p></div>
    @endif

    <form class="page-form" method="post" action="{{ route('patients.store', absolute: false) }}" novalidate>
        @csrf
        @include('patients._form')
        <button class="button" type="submit">Confirmar registro</button>
        <p><a href="{{ route('patients.index') }}">Cancelar</a></p>
    </form>
@endsection
