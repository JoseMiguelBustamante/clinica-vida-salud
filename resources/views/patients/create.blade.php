@extends('layouts.clinic')

@section('title', 'Registrar paciente — Clínica Vida Salud')

@section('content')
    <section class="app-page" aria-labelledby="page-title">
        <p class="app-breadcrumb">General / Pacientes</p>
        <h1 id="page-title">Registrar paciente</h1>
        <p class="lead">El documento de identidad es opcional. Si lo deja vacío, se guarda como ausente y no choca con otros vacíos.</p>

        @if ($errors->any())
            <div class="form-errors" role="alert"><p>{{ $errors->first() }}</p></div>
        @endif

        @if (($accounts ?? collect())->isNotEmpty())
            <form class="page-form" method="get" action="{{ route('patients.create', absolute: false) }}">
                <div class="field">
                    <label for="cuenta">Usar datos de una cuenta de portal</label>
                    <select id="cuenta" name="cuenta">
                        <option value="">Seleccione una cuenta</option>
                        @foreach ($accounts as $account)
                            <option value="{{ $account->id_usuario }}"
                                @selected((string) request('cuenta') === (string) $account->id_usuario)>
                                {{ $account->nombre_usuario }}@if ($account->correo_acceso) — {{ $account->correo_acceso }}@endif
                            </option>
                        @endforeach
                    </select>
                    <p>El portal solo guarda usuario y correo. Al cargar, se vincula la cuenta y se completa el correo de contacto.</p>
                </div>
                <button class="button button-inline" type="submit">Cargar correo y vínculo</button>
            </form>
        @endif

        <form class="page-form" method="post" action="{{ route('patients.store', absolute: false) }}" novalidate>
            @csrf
            @include('patients._form')
            <button class="button" type="submit">Confirmar registro</button>
            <p><a href="{{ route('patients.index') }}">Cancelar</a></p>
        </form>
    </section>
@endsection
