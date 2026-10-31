@extends('layouts.clinic')

@section('title', 'Cita '.$appointment->codigo.' — Clínica Vida Salud')

@section('content')
    <section class="page-head" aria-labelledby="page-title">
        <p class="eyebrow">Citas</p>
        <h1 id="page-title">{{ $appointment->codigo }}</h1>
        <p class="lead">{{ $appointment->estado }} · {{ $appointment->modalidad }}</p>
    </section>

    @if (session('status'))
        <p class="form-errors" role="status">{{ session('status') }}</p>
    @endif
    @if ($errors->any())
        <div class="form-errors" role="alert"><p>{{ $errors->first() }}</p></div>
    @endif

    <section class="implementation">
        <p>Paciente: {{ $appointment->paciente_apellidos }}, {{ $appointment->paciente_nombres }}
            · CI {{ $appointment->paciente_ci ?: 'no indicado' }}</p>
        <p>Especialidad: {{ $appointment->especialidad_nombre }}</p>
        <p>Médico: {{ $appointment->medico_apellidos }}, {{ $appointment->medico_nombres }}
            · {{ $appointment->registro_profesional }}</p>
        <p>Intervalo: {{ \Illuminate\Support\Carbon::parse((string) $appointment->inicio)->timezone('UTC')->format('Y-m-d H:i') }}
            – {{ \Illuminate\Support\Carbon::parse((string) $appointment->fin)->timezone('UTC')->format('H:i') }} UTC</p>
        <p>Motivo: {{ $appointment->motivo_consulta ?: '—' }}</p>
        <p>Programar o reprogramar no confirma la asistencia.</p>
        <p>
            @if (in_array(\App\Modules\Identity\PermissionCatalog::RESCHEDULE_APPOINTMENTS, $identityPermissions ?? [], true)
                && in_array($appointment->estado, ['Programada', 'Confirmada'], true))
                <a class="button button-inline" href="{{ route('appointments.reschedule', $appointment->id_cita) }}">Reprogramar cita</a>
            @endif
            @if (in_array(\App\Modules\Identity\PermissionCatalog::BOOK_APPOINTMENTS, $identityPermissions ?? [], true))
                <a href="{{ route('appointments.create') }}">Programar otra cita</a>
            @endif
            @if (in_array(\App\Modules\Identity\PermissionCatalog::RESCHEDULE_APPOINTMENTS, $identityPermissions ?? [], true))
                · <a href="{{ route('appointments.index') }}">Citas registradas</a>
            @endif
            @if (in_array(\App\Modules\Identity\PermissionCatalog::CONSULT_AVAILABILITY, $identityPermissions ?? [], true))
                · <a href="{{ route('availability.consult') }}">Consultar disponibilidad</a>
            @endif
        </p>
    </section>
@endsection
