@extends('layouts.clinic')

@section('title', 'Panel — Clínica Vida Salud')

@section('content')
    <section class="page-head" aria-labelledby="page-title">
        <p class="eyebrow">Sesión activa</p>
        <h1 id="page-title">Panel de acceso</h1>
        <p class="lead">{{ $user['username'] }}
            @if ($user['role']['name'] ?? null)
                · {{ $user['role']['name'] }}
            @endif
        </p>
    </section>

    <nav class="module-grid" aria-label="Módulos según el rol">
        <a class="module-tile" href="{{ route('home') }}">
            <strong>Inicio</strong>
            <span>Portada pública de la clínica.</span>
        </a>
        <a class="module-tile is-current" href="{{ route('panel') }}" aria-current="page">
            <strong>Panel</strong>
            <span>Resumen de la sesión y del rol.</span>
        </a>
        @if (in_array(\App\Modules\Identity\PermissionCatalog::MANAGE_ACCOUNTS, $user['permissions'], true))
            <a class="module-tile" href="{{ route('accounts.index') }}">
                <strong>Usuarios y roles</strong>
                <span>Registrar, consultar, modificar y activar o desactivar cuentas.</span>
            </a>
        @endif
        @if (in_array(\App\Modules\Identity\PermissionCatalog::MANAGE_PATIENTS, $user['permissions'], true))
            <a class="module-tile" href="{{ route('patients.index') }}">
                <strong>Pacientes</strong>
                <span>Registrar, consultar, modificar y desactivar fichas de pacientes.</span>
            </a>
        @endif
        @if (in_array(\App\Modules\Identity\PermissionCatalog::MANAGE_PHYSICIANS, $user['permissions'], true))
            <a class="module-tile" href="{{ route('physicians.index') }}">
                <strong>Médicos</strong>
                <span>Registro profesional, contacto, estado y especialidades.</span>
            </a>
        @endif
        @if (in_array(\App\Modules\Identity\PermissionCatalog::MANAGE_SPECIALTIES, $user['permissions'], true))
            <a class="module-tile" href="{{ route('specialties.index') }}">
                <strong>Especialidades</strong>
                <span>Catálogo clínico con nombre único y estado.</span>
            </a>
        @endif
        @if (in_array(\App\Modules\Identity\PermissionCatalog::MANAGE_SCHEDULES, $user['permissions'], true))
            <a class="module-tile" href="{{ route('schedules.index') }}">
                <strong>Horarios</strong>
                <span>Días, franjas y duración de citas de cada médico.</span>
            </a>
        @endif
        @if (in_array(\App\Modules\Identity\PermissionCatalog::CONSULT_AVAILABILITY, $user['permissions'], true))
            <a class="module-tile" href="{{ route('availability.consult') }}">
                <strong>Citas</strong>
                <span>Consultar, programar, reprogramar, cancelar, asistencia y marcar atendida.</span>
            </a>
        @elseif (in_array(\App\Modules\Identity\PermissionCatalog::BOOK_APPOINTMENTS, $user['permissions'], true))
            <a class="module-tile" href="{{ route('appointments.create') }}">
                <strong>Citas</strong>
                <span>Programar una cita en un intervalo disponible.</span>
            </a>
        @endif
        @if (in_array(\App\Modules\Identity\PermissionCatalog::CONSULT_AGENDA, $user['permissions'], true)
            || in_array(\App\Modules\Identity\PermissionCatalog::MARK_ATTENDED, $user['permissions'], true))
            <a class="module-tile" href="{{ route('appointments.agenda') }}">
                <strong>Agenda</strong>
                <span>Consultar citas por médico, fecha y estado.</span>
            </a>
        @endif
        @if (in_array(\App\Modules\Identity\PermissionCatalog::CONSULT_HISTORY, $user['permissions'], true))
            <a class="module-tile" href="{{ route('appointments.history') }}">
                <strong>Historial</strong>
                <span>Consultar las citas registradas de un paciente.</span>
            </a>
        @endif
        @if (in_array(\App\Modules\Identity\PermissionCatalog::GENERATE_REPORTS, $user['permissions'], true))
            <a class="module-tile" href="{{ route('reports.generate') }}">
                <strong>Reportes</strong>
                <span>Consultar totales y listados administrativos de citas y pacientes.</span>
            </a>
        @endif
        @if (in_array(\App\Modules\Identity\PermissionCatalog::UPDATE_PROFILE, $user['permissions'], true))
            <a class="module-tile" href="{{ route('portal.profile') }}">
                <strong>Perfil y preferencias</strong>
                <span>Actualizar la cuenta propia y los recordatorios de citas.</span>
            </a>
        @endif
    </nav>

    <section class="implementation" aria-labelledby="permissions-title">
        <h2 id="permissions-title">Permisos del rol</h2>
        @if ($user['permissions'] !== [])
            <ul class="module-list">
                @foreach ($user['permissions'] as $permission)
                    <li><code>{{ $permission }}</code></li>
                @endforeach
            </ul>
            @if (($user['role']['name'] ?? null) === \App\Modules\Identity\PermissionCatalog::PATIENT_ROLE)
                <p>El acceso a citas e información del paciente requiere un vínculo aprobado.</p>
            @endif
        @elseif (($user['role']['name'] ?? null) === \App\Modules\Identity\PermissionCatalog::PATIENT_ROLE)
            <p>Su cuenta de portal está registrada. El acceso a citas e información del paciente requiere un vínculo aprobado. Esa aprobación no forma parte del registro.</p>
        @else
            <p>Este rol no tiene permisos clínicos asignados. Configurar horarios corresponde al administrador; la agenda del médico se habilita con las citas.</p>
        @endif
    </section>
@endsection
