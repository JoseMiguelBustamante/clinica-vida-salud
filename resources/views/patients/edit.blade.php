@extends('layouts.clinic')

@section('title', 'Modificar paciente — Clínica Vida Salud')

@section('content')
    <section class="app-page" aria-labelledby="page-title">
        <p class="app-breadcrumb">General / Pacientes</p>
        <h1 id="page-title">Modificar paciente</h1>
        <p class="lead">El CI, si se indica, no puede coincidir con el de otro paciente.</p>

        @if ($errors->any())
            <div class="form-errors" role="alert"><p>{{ $errors->first() }}</p></div>
        @endif

        <form class="page-form" method="post" action="{{ route('patients.update', $patient->id_paciente, false) }}" novalidate>
            @csrf
            @include('patients._form', ['patient' => $patient])
            <button class="button" type="submit">Guardar cambios</button>
            <p><a href="{{ route('patients.show', $patient->id_paciente) }}">Cancelar</a></p>
        </form>
    </section>
@endsection
