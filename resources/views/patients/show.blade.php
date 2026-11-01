@extends('layouts.clinic')

@section('title', 'Paciente — Clínica Vida Salud')

@section('content')
    <section class="app-page" aria-labelledby="page-title">
        <p class="app-breadcrumb">General / Pacientes</p>
        <div class="page-toolbar">
            <div>
                <h1 id="page-title">{{ $patient->nombres }} {{ $patient->apellidos }}</h1>
                <p class="lead">
                    <span class="status-pill {{ $patient->activo ? 'is-active' : 'is-inactive' }}">
                        {{ $patient->activo ? 'Activo' : 'Inactivo' }}
                    </span>
                    · CI {{ $patient->ci ?: 'no indicado' }}
                </p>
            </div>
            <nav class="row-actions" aria-label="Acciones">
                <a class="row-action" href="{{ route('patients.edit', $patient->id_paciente) }}">Modificar</a>
                @if (in_array(\App\Modules\Identity\PermissionCatalog::CONSULT_HISTORY, $identityPermissions ?? [], true))
                    <a class="row-action" href="{{ route('appointments.history', ['id_paciente' => $patient->id_paciente]) }}">Historial</a>
                @endif
                <a class="row-action" href="{{ route('patients.index') }}">Volver al listado</a>
            </nav>
        </div>

        @if (session('status'))
            <p class="form-status" role="status">{{ session('status') }}</p>
        @endif
        @if ($errors->any())
            <div class="form-errors" role="alert"><p>{{ $errors->first() }}</p></div>
        @endif

        <article class="list-card detail-card">
            <dl class="detail-list">
                <div>
                    <dt>Fecha de nacimiento</dt>
                    <dd>{{ $patient->fecha_nacimiento ? \Illuminate\Support\Str::of($patient->fecha_nacimiento)->substr(0, 10) : '—' }}</dd>
                </div>
                <div>
                    <dt>Sexo</dt>
                    <dd>{{ $patient->sexo ?: '—' }}</dd>
                </div>
                <div>
                    <dt>Teléfono</dt>
                    <dd>{{ $patient->telefono ?: '—' }}</dd>
                </div>
                <div>
                    <dt>Correo de contacto</dt>
                    <dd>{{ $patient->correo_contacto ?: '—' }}</dd>
                </div>
                <div>
                    <dt>Dirección</dt>
                    <dd>{{ $patient->direccion ?: '—' }}</dd>
                </div>
                <div>
                    <dt>Cuenta de portal</dt>
                    <dd>{{ $patient->id_usuario ? 'vinculada' : 'sin vincular' }}</dd>
                </div>
            </dl>
        </article>

        @if ($patient->activo)
            <form class="status-form" method="post" action="{{ route('patients.deactivate', $patient->id_paciente, false) }}">
                @csrf
                <button class="button button-inline" type="submit">Desactivar</button>
            </form>
        @else
            <form class="status-form" method="post" action="{{ route('patients.activate', $patient->id_paciente, false) }}">
                @csrf
                <button class="button button-inline" type="submit">Activar</button>
            </form>
        @endif
    </section>
@endsection
