@extends('layouts.clinic')

@section('title', 'Panel de acceso — Clínica Vida Salud')

@php
    $P = \App\Modules\Identity\PermissionCatalog::class;
    $permissions = $user['permissions'];
    $roleName = $user['role']['name'] ?? null;
    $has = static fn (string $code): bool => in_array($code, $permissions, true);
@endphp

@section('content')
    <section class="app-page" aria-labelledby="page-title">
        <p class="app-breadcrumb">Sesión activa</p>
        <h1 id="page-title">Panel de acceso</h1>
        <p class="lead">{{ $user['username'] }}
            @if ($roleName)
                · {{ $roleName }}
            @endif
        </p>

        <nav class="module-grid" aria-label="Accesos frecuentes">
            @if ($has($P::CONSULT_AVAILABILITY))
                <a class="module-tile" href="{{ route('availability.consult') }}">
                    <strong>Disponibilidad</strong>
                    <span>Consultar intervalos libres por médico y fecha. Ver un hueco no lo reserva.</span>
                </a>
            @endif
            @if ($has($P::MANAGE_PATIENTS))
                <a class="module-tile" href="{{ route('patients.index') }}">
                    <strong>Pacientes</strong>
                    <span>Registrar, consultar, modificar y desactivar fichas de pacientes.</span>
                </a>
            @endif
            @if ($has($P::RESCHEDULE_APPOINTMENTS) || $has($P::BOOK_APPOINTMENTS))
                <a class="module-tile" href="{{ $has($P::RESCHEDULE_APPOINTMENTS) ? route('appointments.index') : route('appointments.create') }}">
                    <strong>Citas</strong>
                    <span>Programar, consultar y dar seguimiento a las citas registradas.</span>
                </a>
            @endif
            @if ($has($P::CONSULT_AGENDA) || $has($P::MARK_ATTENDED))
                <a class="module-tile" href="{{ route('appointments.agenda') }}">
                    <strong>Agenda</strong>
                    <span>Consultar citas por médico, fecha y estado.</span>
                </a>
            @endif
            @if ($has($P::CONSULT_HISTORY))
                <a class="module-tile" href="{{ route('appointments.history') }}">
                    <strong>Historial</strong>
                    <span>Consultar las citas registradas de un paciente.</span>
                </a>
            @endif
            @if ($has($P::MANAGE_PHYSICIANS))
                <a class="module-tile" href="{{ route('physicians.index') }}">
                    <strong>Médicos</strong>
                    <span>Registro profesional, contacto, estado y especialidades.</span>
                </a>
            @endif
            @if ($has($P::MANAGE_SPECIALTIES))
                <a class="module-tile" href="{{ route('specialties.index') }}">
                    <strong>Especialidades</strong>
                    <span>Catálogo clínico con nombre único y estado.</span>
                </a>
            @endif
            @if ($has($P::MANAGE_SCHEDULES))
                <a class="module-tile" href="{{ route('schedules.index') }}">
                    <strong>Horarios</strong>
                    <span>Días, franjas y duración de citas de cada médico.</span>
                </a>
            @endif
            @if ($has($P::GENERATE_REPORTS))
                <a class="module-tile" href="{{ route('reports.generate') }}">
                    <strong>Reportes</strong>
                    <span>Consultar totales y listados administrativos de citas y pacientes.</span>
                </a>
            @endif
            @if ($has($P::MANAGE_ACCOUNTS))
                <a class="module-tile" href="{{ route('accounts.index') }}">
                    <strong>Usuarios y roles</strong>
                    <span>Registrar, consultar, modificar y activar o desactivar cuentas.</span>
                </a>
            @endif
            @if ($has($P::UPDATE_PROFILE))
                <a class="module-tile" href="{{ route('portal.profile') }}">
                    <strong>Perfil y preferencias</strong>
                    <span>Actualizar la cuenta propia y los recordatorios de citas.</span>
                </a>
            @endif
            @if ($has($P::DECLARE_HEALTH))
                <a class="module-tile" href="{{ route('patients.declared') }}">
                    <strong>Información médica</strong>
                    <span>Consultar y actualizar la declaración personal de salud.</span>
                </a>
            @endif
            @if ($has($P::CONSULT_CLINICAL_HISTORY))
                <a class="module-tile" href="{{ route('appointments.clinical-history') }}">
                    <strong>Historial médico</strong>
                    <span>Consultar los registros propios autorizados en el portal.</span>
                </a>
            @endif
        </nav>

        @if ($permissions === [] && $roleName === $P::PATIENT_ROLE)
            <p>Su cuenta de portal está registrada. El acceso a citas e información del paciente requiere un vínculo aprobado. Esa aprobación no forma parte del registro.</p>
        @elseif ($permissions === [])
            <p>Este rol no tiene permisos clínicos asignados. Configurar horarios corresponde al administrador; la agenda del médico se habilita con las citas.</p>
        @elseif ($roleName === $P::PATIENT_ROLE)
            <p>El acceso a citas e información del paciente requiere un vínculo aprobado.</p>
        @endif
    </section>
@endsection
