@php
    $codes = $identityPermissions ?? [];
    $has = static fn (string $code): bool => in_array($code, $codes, true);
    $P = \App\Modules\Identity\PermissionCatalog::class;
    $identityUser = request()->attributes->get('identity');
@endphp
<aside class="app-sidebar" aria-label="Módulos">
    <a class="login-logo app-brand" href="{{ route('panel') }}">
        <span class="brand-badge" aria-hidden="true">+</span>
        <span>Clínica Vida Salud</span>
    </a>

    <nav class="app-nav">
        <p class="app-nav-label">General</p>
        <a class="app-nav-link @if (request()->routeIs('panel')) is-current @endif" href="{{ route('panel') }}">Inicio</a>
        @if ($has($P::MANAGE_PATIENTS))
            <a class="app-nav-link @if (request()->routeIs('patients.*') && ! request()->routeIs('patients.declared')) is-current @endif" href="{{ route('patients.index') }}">Pacientes</a>
        @endif

        @if ($has($P::MANAGE_PHYSICIANS) || $has($P::MANAGE_SPECIALTIES) || $has($P::MANAGE_SCHEDULES) || $has($P::CONSULT_AVAILABILITY) || $has($P::BOOK_APPOINTMENTS) || $has($P::RESCHEDULE_APPOINTMENTS) || $has($P::CONSULT_AGENDA) || $has($P::MARK_ATTENDED) || $has($P::CONSULT_HISTORY))
            <p class="app-nav-label">Atención</p>
        @endif
        @if ($has($P::MANAGE_PHYSICIANS))
            <a class="app-nav-link @if (request()->routeIs('physicians.*')) is-current @endif" href="{{ route('physicians.index') }}">Médicos</a>
        @endif
        @if ($has($P::MANAGE_SPECIALTIES))
            <a class="app-nav-link @if (request()->routeIs('specialties.*')) is-current @endif" href="{{ route('specialties.index') }}">Especialidades</a>
        @endif
        @if ($has($P::MANAGE_SCHEDULES))
            <a class="app-nav-link @if (request()->routeIs('schedules.*')) is-current @endif" href="{{ route('schedules.index') }}">Horarios</a>
        @endif
        @if ($has($P::CONSULT_AVAILABILITY))
            <a class="app-nav-link @if (request()->routeIs('availability.consult')) is-current @endif" href="{{ route('availability.consult') }}">Disponibilidad</a>
        @endif
        @if ($has($P::RESCHEDULE_APPOINTMENTS))
            <a class="app-nav-link @if (request()->routeIs('appointments.index', 'appointments.show', 'appointments.create', 'appointments.reschedule', 'appointments.cancel', 'appointments.attendance', 'appointments.attend')) is-current @endif" href="{{ route('appointments.index') }}">Citas</a>
        @elseif ($has($P::BOOK_APPOINTMENTS))
            <a class="app-nav-link @if (request()->routeIs('appointments.*')) is-current @endif" href="{{ route('appointments.create') }}">Citas</a>
        @endif
        @if ($has($P::CONSULT_AGENDA) || $has($P::MARK_ATTENDED))
            <a class="app-nav-link @if (request()->routeIs('appointments.agenda')) is-current @endif" href="{{ route('appointments.agenda') }}">Agenda</a>
        @endif
        @if ($has($P::CONSULT_HISTORY))
            <a class="app-nav-link @if (request()->routeIs('appointments.history')) is-current @endif" href="{{ route('appointments.history') }}">Historial</a>
        @endif

        @if ($has($P::GENERATE_REPORTS) || $has($P::MANAGE_ACCOUNTS))
            <p class="app-nav-label">Administración</p>
        @endif
        @if ($has($P::GENERATE_REPORTS))
            <a class="app-nav-link @if (request()->routeIs('reports.*')) is-current @endif" href="{{ route('reports.generate') }}">Reportes</a>
        @endif
        @if ($has($P::MANAGE_ACCOUNTS))
            <a class="app-nav-link @if (request()->routeIs('accounts.*')) is-current @endif" href="{{ route('accounts.index') }}">Usuarios</a>
        @endif

        @if ($has($P::UPDATE_PROFILE) || $has($P::DECLARE_HEALTH) || $has($P::CONSULT_CLINICAL_HISTORY))
            <p class="app-nav-label">Portal</p>
        @endif
        @if ($has($P::UPDATE_PROFILE))
            <a class="app-nav-link @if (request()->routeIs('portal.profile')) is-current @endif" href="{{ route('portal.profile') }}">Perfil</a>
        @endif
        @if ($has($P::DECLARE_HEALTH))
            <a class="app-nav-link @if (request()->routeIs('patients.declared')) is-current @endif" href="{{ route('patients.declared') }}">Información médica</a>
        @endif
        @if ($has($P::CONSULT_CLINICAL_HISTORY))
            <a class="app-nav-link @if (request()->routeIs('appointments.clinical-history', 'appointments.clinical-history.show')) is-current @endif" href="{{ route('appointments.clinical-history') }}">Historial médico</a>
        @endif
    </nav>

    <div class="app-sidebar-end">
        @if ($identityUser?->nombre_usuario)
            <p class="app-session-name">{{ $identityUser->nombre_usuario }}</p>
        @endif
        <form method="post" action="{{ route('logout', absolute: false) }}">
            @csrf
            <button class="app-nav-link app-logout" type="submit">Cerrar sesión</button>
        </form>
    </div>
</aside>
