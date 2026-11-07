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
            [$has($P::MANAGE_ACCOUNTS), 'Usuarios y roles', 'accounts.index', 'Registrar y mantener cuentas de acceso.', 'users'],
            [$has($P::GENERATE_REPORTS), 'Reportes', 'reports.generate', 'Consultar totales y listados administrativos.', 'reports'],
            [$has($P::MANAGE_PATIENTS), 'Pacientes', 'patients.index', 'Registrar y mantener fichas de pacientes.', 'patients'],
            [$has($P::MANAGE_PHYSICIANS), 'Médicos', 'physicians.index', 'Registro profesional y especialidades.', 'physicians'],
            [$has($P::MANAGE_SPECIALTIES), 'Especialidades', 'specialties.index', 'Catálogo clínico con nombre único.', 'specialties'],
            [$has($P::MANAGE_SCHEDULES), 'Horarios', 'schedules.index', 'Franjas y duración de citas por médico.', 'schedules'],
            [$has($P::CONSULT_AVAILABILITY), 'Disponibilidad', 'availability.consult', 'Consultar intervalos libres. Ver un hueco no lo reserva.', 'availability'],
        ],
        $P::RECEPTIONIST_ROLE => [
            [$has($P::MANAGE_PATIENTS), 'Pacientes', 'patients.index', 'Registrar y mantener fichas de pacientes.', 'patients'],
            [$has($P::CONSULT_AVAILABILITY), 'Disponibilidad', 'availability.consult', 'Consultar intervalos libres. Ver un hueco no lo reserva.', 'availability'],
            [$has($P::RESCHEDULE_APPOINTMENTS) || $has($P::BOOK_APPOINTMENTS), 'Citas', $has($P::RESCHEDULE_APPOINTMENTS) ? 'appointments.index' : 'appointments.create', 'Programar y dar seguimiento a las citas.', 'appointments'],
            [$has($P::CONSULT_AGENDA) || $has($P::MARK_ATTENDED), 'Agenda', 'appointments.agenda', 'Consultar citas por médico, fecha y estado.', 'agenda'],
            [$has($P::CONSULT_HISTORY), 'Historial', 'appointments.history', 'Consultar las citas registradas de un paciente.', 'history'],
        ],
        $P::PHYSICIAN_ROLE => [
            [$has($P::CONSULT_AGENDA) || $has($P::MARK_ATTENDED), 'Agenda', 'appointments.agenda', 'Consultar la agenda del día y registrar la atención.', 'agenda'],
            [$has($P::CONSULT_HISTORY), 'Historial', 'appointments.history', 'Consultar las citas registradas de un paciente.', 'history'],
        ],
        $P::PATIENT_ROLE => [
            [$has($P::UPDATE_PROFILE), 'Perfil y preferencias', 'portal.profile', 'Actualizar la cuenta propia y los recordatorios.', 'profile'],
            [$has($P::DECLARE_HEALTH), 'Información médica', 'patients.declared', 'Consultar y actualizar la declaración personal de salud.', 'health'],
            [$has($P::CONSULT_CLINICAL_HISTORY), 'Historial médico', 'appointments.clinical-history', 'Consultar los registros propios autorizados.', 'history'],
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
            'icon' => $candidate[4],
        ];
    }
@endphp

@section('content')
    <section class="app-page panel-home" aria-labelledby="page-title">
        <header class="panel-home-head">
            <p class="app-breadcrumb">Sesión activa</p>
            <h1 id="page-title">Inicio</h1>
            <p class="lead">
                <span class="panel-user">{{ $user['username'] }}</span>
                @if ($roleName)
                    <span class="panel-role-pill">{{ $roleName }}</span>
                @endif
            </p>
        </header>

        <article class="session-card">
            <div class="session-card-copy">
                <p class="session-card-label">Resumen de rol</p>
                <h2>{{ $roleName ?: 'Sesión' }}</h2>
                <p>{{ $roleSummary }}</p>
            </div>
        </article>

        @if ($actions !== [])
            <section class="panel-next" aria-labelledby="next-title">
                <h2 id="next-title" class="session-next">Siguiente paso</h2>
                <nav class="session-actions" aria-label="Siguiente paso">
                    @foreach ($actions as $action)
                        <a class="session-action" href="{{ $action['href'] }}">
                            <span class="session-action-icon" aria-hidden="true" data-icon="{{ $action['icon'] }}">
                                @switch($action['icon'])
                                    @case('users')
                                        <svg viewBox="0 0 24 24"><path fill="currentColor" d="M9 11a3.5 3.5 0 1 0-3.5-3.5A3.5 3.5 0 0 0 9 11Zm6.5-.5a3 3 0 1 0-3-3 3 3 0 0 0 3 3ZM2.5 19.5c0-2.8 2.9-4.5 6.5-4.5s6.5 1.7 6.5 4.5V21H2.5v-1.5Zm12 .2c.5-.1 1-.2 1.5-.2 3.2 0 5.5 1.4 5.5 3V21h-7v-1.3Z"/></svg>
                                        @break
                                    @case('reports')
                                        <svg viewBox="0 0 24 24"><path fill="currentColor" d="M5 3h14a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2Zm2 12h2v4H7v-4Zm4-6h2v10h-2V9Zm4 3h2v7h-2v-7Z"/></svg>
                                        @break
                                    @case('patients')
                                        <svg viewBox="0 0 24 24"><path fill="currentColor" d="M12 12a4 4 0 1 0-4-4 4 4 0 0 0 4 4Zm0 2c-3.3 0-7 1.7-7 4v2h14v-2c0-2.3-3.7-4-7-4Z"/></svg>
                                        @break
                                    @case('physicians')
                                        <svg viewBox="0 0 24 24"><path fill="currentColor" d="M12 2a3 3 0 0 1 3 3v1h2.5A2.5 2.5 0 0 1 20 8.5V11h-2v7.5A3.5 3.5 0 0 1 14.5 22h-5A3.5 3.5 0 0 1 6 18.5V11H4V8.5A2.5 2.5 0 0 1 6.5 6H9V5a3 3 0 0 1 3-3Zm-1 11h2v2h2v2h-2v2h-2v-2H9v-2h2v-2Z"/></svg>
                                        @break
                                    @case('specialties')
                                        <svg viewBox="0 0 24 24"><path fill="currentColor" d="M12 2 4 5v6.5C4 16.2 7.4 20.5 12 22c4.6-1.5 8-5.8 8-10.5V5l-8-3Zm0 4.2 5 1.9v3.4c0 3.2-2.2 6.2-5 7.4-2.8-1.2-5-4.2-5-7.4V8.1l5-1.9Z"/></svg>
                                        @break
                                    @case('schedules')
                                        <svg viewBox="0 0 24 24"><path fill="currentColor" d="M7 3h2v2h6V3h2v2h3v16H4V5h3V3Zm11 6H6v10h12V9Z"/></svg>
                                        @break
                                    @case('availability')
                                        <svg viewBox="0 0 24 24"><path fill="currentColor" d="M12 4a8 8 0 1 1-8 8 8 8 0 0 1 8-8Zm.8 3.2h-1.6v5.1l4.2 2.5.8-1.3-3.4-2V7.2Z"/></svg>
                                        @break
                                    @case('appointments')
                                        <svg viewBox="0 0 24 24"><path fill="currentColor" d="M7 3h2v2h6V3h2v2h3v16H4V5h3V3Zm2 8h2v2H9v-2Zm4 0h2v2h-2v-2Zm-4 4h2v2H9v-2Zm4 0h2v2h-2v-2Z"/></svg>
                                        @break
                                    @case('agenda')
                                        <svg viewBox="0 0 24 24"><path fill="currentColor" d="M4 4h16v2H4V4Zm0 4h10v2H4V8Zm0 4h16v2H4v-2Zm0 4h10v2H4v-2Z"/></svg>
                                        @break
                                    @case('history')
                                        <svg viewBox="0 0 24 24"><path fill="currentColor" d="M12 4a8 8 0 1 1-7.5 10.7l1.5-.5A6.5 6.5 0 1 0 12 5.5V8l4-3.5L12 1v3Zm-1 4h2v5.2l3.2 1.9-.9 1.5L11 14.2V8Z"/></svg>
                                        @break
                                    @case('profile')
                                        <svg viewBox="0 0 24 24"><path fill="currentColor" d="M12 12a4.5 4.5 0 1 0-4.5-4.5A4.5 4.5 0 0 0 12 12Zm0 2.25c-3.6 0-6.75 1.8-6.75 4V19.5h13.5v-1.25c0-2.2-3.15-4-6.75-4Z"/></svg>
                                        @break
                                    @case('health')
                                        <svg viewBox="0 0 24 24"><path fill="currentColor" d="M11 4h2v7h7v2h-7v7h-2v-7H4v-2h7V4Z"/></svg>
                                        @break
                                    @default
                                        <svg viewBox="0 0 24 24"><path fill="currentColor" d="M5 11h14v2H5v-2Z"/></svg>
                                @endswitch
                            </span>
                            <strong>{{ $action['title'] }}</strong>
                            <span>{{ $action['hint'] }}</span>
                        </a>
                    @endforeach
                </nav>
            </section>
        @endif

        @if ($permissions === [] && $roleName === $P::PATIENT_ROLE)
            <p class="panel-note">Su cuenta de portal está registrada. El acceso a citas e información del paciente requiere un vínculo aprobado. Esa aprobación no forma parte del registro.</p>
        @elseif ($permissions === [])
            <p class="panel-note">Este rol no tiene permisos clínicos asignados. Configurar horarios corresponde al administrador; la agenda del médico se habilita con las citas.</p>
        @elseif ($roleName === $P::PATIENT_ROLE)
            <p class="panel-note">El acceso a citas e información del paciente requiere un vínculo aprobado.</p>
        @endif
    </section>
@endsection
