@extends('layouts.clinic')

@section('title', 'Paciente — Clínica Vida Salud')

@section('content')
    <section class="page-head" aria-labelledby="page-title">
        <p class="eyebrow">Gestión clínica</p>
        <h1 id="page-title">{{ $patient->nombres }} {{ $patient->apellidos }}</h1>
        <p class="lead">{{ $patient->activo ? 'Activo' : 'Inactivo' }}
            · CI {{ $patient->ci ?: 'no indicado' }}</p>
    </section>

    @if (session('status'))
        <p class="form-errors" role="status">{{ session('status') }}</p>
    @endif
    @if ($errors->any())
        <div class="form-errors" role="alert"><p>{{ $errors->first() }}</p></div>
    @endif

    <section class="implementation">
        <p>Fecha de nacimiento: {{ $patient->fecha_nacimiento ? \Illuminate\Support\Str::of($patient->fecha_nacimiento)->substr(0, 10) : '—' }}</p>
        <p>Sexo: {{ $patient->sexo ?: '—' }}</p>
        <p>Teléfono: {{ $patient->telefono ?: '—' }}</p>
        <p>Correo de contacto: {{ $patient->correo_contacto ?: '—' }}</p>
        <p>Dirección: {{ $patient->direccion ?: '—' }}</p>
        <p>Cuenta de portal: {{ $patient->id_usuario ? 'vinculada' : 'sin vincular' }}</p>
        <p><a href="{{ route('patients.edit', $patient->id_paciente) }}">Modificar</a>
            @if (in_array(\App\Modules\Identity\PermissionCatalog::CONSULT_HISTORY, $identityPermissions ?? [], true))
                · <a href="{{ route('appointments.history', ['id_paciente' => $patient->id_paciente]) }}">Historial de citas</a>
            @endif
            · <a href="{{ route('patients.index') }}">Volver al listado</a></p>
        @if ($patient->activo)
            <form method="post" action="{{ route('patients.deactivate', $patient->id_paciente, false) }}">
                @csrf
                <button class="button button-inline" type="submit">Desactivar</button>
            </form>
        @else
            <form method="post" action="{{ route('patients.activate', $patient->id_paciente, false) }}">
                @csrf
                <button class="button button-inline" type="submit">Activar</button>
            </form>
        @endif
    </section>
@endsection
