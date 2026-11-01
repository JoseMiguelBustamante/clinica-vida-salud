@extends('layouts.clinic')

@section('title', 'Modificar médico — Clínica Vida Salud')

@section('content')
    <section class="app-page" aria-labelledby="page-title">
        <p class="app-breadcrumb">Atención / Médicos</p>
        <h1 id="page-title">Modificar médico</h1>
        <p class="lead">Puede conservar una especialidad ya asociada aunque esté inactiva. No puede agregar una inactiva nueva.</p>

        @if ($errors->any())
            <div class="form-errors" role="alert"><p>{{ $errors->first() }}</p></div>
        @endif

        <form class="page-form" method="post" action="{{ route('physicians.update', $physician->id_medico, false) }}" novalidate>
            @csrf
            @include('medical-staff.physicians._form', ['physician' => $physician, 'specialties' => $specialties])
            <button class="button" type="submit">Guardar cambios</button>
            <p><a href="{{ route('physicians.show', $physician->id_medico) }}">Cancelar</a></p>
        </form>
    </section>
@endsection
