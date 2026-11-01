@extends('layouts.clinic')

@section('title', 'Inicio — Clínica Vida Salud')

@php
    $P = \App\Modules\Identity\PermissionCatalog::class;
    $permissions = $user['permissions'];
    $roleName = $user['role']['name'] ?? null;
    $has = static fn (string $code): bool => in_array($code, $permissions, true);
    $roleSummary = match ($roleName) {
        $P::ADMINISTRATOR_ROLE => 'Administra cuentas, catálogos y reportes autorizados. El menú de la izquierda abre cada módulo; aquí queda el resumen de esta sesión.',
        $P::RECEPTIONIST_ROLE => 'Gestiona pacientes y citas según los permisos de la cuenta. El menú lateral abre cada módulo; aquí queda el siguiente paso de esta sesión.',
        $P::PHYSICIAN_ROLE => 'Consulta su agenda y registra la atención. El menú lateral abre los módulos habilitados para esta cuenta.',
        $P::PATIENT_ROLE => 'Portal del paciente. El menú abre perfil e información autorizada; el acceso a citas clínicas requiere un vínculo aprobado.',
        default => 'El menú de la izquierda abre los módulos habilitados de esta sesión.',
    };
    $candidates = match ($roleName) {
        $P::ADMINISTRATOR_ROLE => [
            [$has($P::MANAGE_ACCOUNTS), 'Usuarios y roles', 'accounts.index', 'Registrar y mantener cuentas de acceso.'],
            [$has($P::GENERATE_REPORTS), 'Reportes', 'reports.generate', 'Consultar totales y listados administrativos.'],
            [$has($P::MANAGE_PATIENTS), 'Pacientes', 'patients.index', 'Registrar y mantener fichas de pacientes.'],
            [$has($P::MANAGE_PHYSICIANS), 'Médicos', 'physicians.index', 'Registro profesional y especialidades.'],
            [$has($P::MANAGE_SPECIALTIES), 'Especialidades', 'specialties.index', 'Catálogo clínico con nombre único.'],
            [$has($P::MANAGE_SCHEDULES), 'Horarios', 'schedules.index', 'Franjas y duración de citas por médico.'],
            [$has($P::CONSULT_AVAILABILITY), 'Disponibilidad', 'availability.consult', 'Consultar intervalos libres. Ver un hueco no lo reserva.'],
        ],
        $P::RECEPTIONIST_ROLE => [
            [$has($P::MANAGE_PATIENTS), 'Pacientes', 'patients.index', 'Registrar y mantener fichas de pacientes.'],
            [$has($P::CONSULT_AVAILABILITY), 'Disponibilidad', 'availability.consult', 'Consultar intervalos libres. Ver un hueco no lo reserva.'],
            [$has($P::RESCHEDULE_APPOINTMENTS) || $has($P::BOOK_APPOINTMENTS), 'Citas', $has($P::RESCHEDULE_APPOINTMENTS) ? 'appointments.index' : 'appointments.create', 'Programar y dar seguimiento a las citas.'],
            [$has($P::CONSULT_AGENDA) || $has($P::MARK_ATTENDED), 'Agenda', 'appointments.agenda', 'Consultar citas por médico, fecha y estado.'],
            [$has($P::CONSULT_HISTORY), 'Historial', 'appointments.history', 'Consultar las citas registradas de un paciente.'],
        ],
        $P::PHYSICIAN_ROLE => [
            [$has($P::CONSULT_AGENDA) || $has($P::MARK_ATTENDED), 'Agenda', 'appointments.agenda', 'Consultar la agenda del día y registrar la atención.'],
            [$has($P::CONSULT_HISTORY), 'Historial', 'appointments.history', 'Consultar las citas registradas de un paciente.'],
        ],
        $P::PATIENT_ROLE => [
            [$has($P::UPDATE_PROFILE), 'Perfil y preferencias', 'portal.profile', 'Actualizar la cuenta propia y los recordatorios.'],
            [$has($P::DECLARE_HEALTH), 'Información médica', 'patients.declared', 'Consultar y actualizar la declaración personal de salud.'],
            [$has($P::CONSULT_CLINICAL_HISTORY), 'Historial médico', 'appointments.clinical-history', 'Consultar los registros propios autorizados.'],
        ],
        default => [],
    };
    $actions = [];
    foreach ($candidates as $candidate) {
        if ($candidate[0] !== true || count($actions) >= 3) {
            continue;
        }
        $actions[] = [
            'title' => $candidate[1],
            'href' => route($candidate[2]),
            'hint' => $candidate[3],
        ];
    }
@endphp

@section('content')
    <section class="app-page" aria-labelledby="page-title">
        <p class="app-breadcrumb">Sesión activa</p>
        <h1 id="page-title">Inicio</h1>
        <p class="lead">{{ $user['username'] }}
            @if ($roleName)
                · {{ $roleName }}
            @endif
        </p>

        <article class="session-card">
            <h2>{{ $roleName ?: 'Sesión' }}</h2>
            <p>{{ $roleSummary }}</p>
        </article>

        @if ($actions !== [])
            <h2 class="session-next">Siguiente paso</h2>
            <nav class="session-actions" aria-label="Siguiente paso">
                @foreach ($actions as $action)
                    <a class="session-action" href="{{ $action['href'] }}">
                        <strong>{{ $action['title'] }}</strong>
                        <span>{{ $action['hint'] }}</span>
                    </a>
                @endforeach
            </nav>
        @endif

        @if ($permissions === [] && $roleName === $P::PATIENT_ROLE)
            <p>Su cuenta de portal está registrada. El acceso a citas e información del paciente requiere un vínculo aprobado. Esa aprobación no forma parte del registro.</p>
        @elseif ($permissions === [])
            <p>Este rol no tiene permisos clínicos asignados. Configurar horarios corresponde al administrador; la agenda del médico se habilita con las citas.</p>
        @elseif ($roleName === $P::PATIENT_ROLE)
            <p>El acceso a citas e información del paciente requiere un vínculo aprobado.</p>
        @endif
    </section>
@endsection
