@extends('layouts.clinic')

@section('title', 'Modificar horario — Clínica Vida Salud')

@section('content')
    <section class="app-page" aria-labelledby="page-title">
        <p class="app-breadcrumb">Atención / Horarios</p>
        <h1 id="page-title">Modificar horario</h1>
        <p class="lead">Si hay citas programadas o confirmadas que quedarían fuera de la nueva franja, el cambio no se aplica.</p>

        @if ($errors->any())
            <div class="form-errors" role="alert"><p>{{ $errors->first() }}</p></div>
        @endif

        <form class="page-form" method="post" action="{{ route('schedules.update', $schedule->id_horario, false) }}" novalidate>
            @csrf
            @include('schedules._form', ['schedule' => $schedule, 'weekdays' => $weekdays])
            <button class="button" type="submit">Guardar cambios</button>
            <p><a href="{{ route('schedules.show', $schedule->id_horario) }}">Cancelar</a></p>
        </form>
    </section>
@endsection
