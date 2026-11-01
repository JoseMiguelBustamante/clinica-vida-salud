@extends('layouts.clinic')

@section('title', 'Registrar horario — Clínica Vida Salud')

@section('content')
    <section class="app-page" aria-labelledby="page-title">
        <p class="app-breadcrumb">Atención / Horarios</p>
        <h1 id="page-title">Registrar horario</h1>
        <p class="lead">La duración debe caber en intervalos completos. Por ejemplo, 08:00–12:00 admite 30 o 60 minutos, no 45.</p>

        @if ($errors->any())
            <div class="form-errors" role="alert"><p>{{ $errors->first() }}</p></div>
        @endif

        <form class="page-form" method="post" action="{{ route('schedules.store', absolute: false) }}" novalidate>
            @csrf
            @include('schedules._form')
            <button class="button" type="submit">Confirmar registro</button>
            <p><a href="{{ route('schedules.index') }}">Cancelar</a></p>
        </form>
    </section>
@endsection
