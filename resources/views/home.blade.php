@extends('layouts.clinic')

@section('title', 'Clínica Vida Salud — Gestión de citas')

@section('content')
    <section class="intro" aria-labelledby="page-title">
        <p class="eyebrow">Clínica Vida Salud</p>
        <h1 id="page-title">Gestión de citas médicas</h1>
        <p class="lead">Coordine pacientes, médicos, horarios y citas desde un solo sistema web.</p>
        <nav class="intro-actions" aria-label="Acceso">
            <a class="button" href="{{ route('login') }}">Iniciar sesión</a>
            <a class="section-link" href="{{ route('portal.register') }}">Crear cuenta</a>
        </nav>
    </section>
@endsection
