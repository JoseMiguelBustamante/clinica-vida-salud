@extends('layouts.clinic')

@section('title', 'Acceso denegado — Clínica Vida Salud')

@section('content')
    <section class="page-head" aria-labelledby="page-title">
        <h1 id="page-title">Acceso denegado</h1>
        <p class="lead">{{ $message }}</p>
        <p><a class="section-link" href="{{ route('panel') }}">Volver al panel</a></p>
    </section>
@endsection
