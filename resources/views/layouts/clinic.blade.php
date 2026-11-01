<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="description" content="Clínica Vida Salud, gestión de citas médicas.">
    <title>@yield('title', 'Clínica Vida Salud')</title>
    <link rel="stylesheet" href="/css/clinic.css?v={{ filemtime(public_path('css/clinic.css')) }}">
</head>
<body>
    <a class="skip-link" href="#main-content">Ir al contenido principal</a>
    <header class="site-header">
        <div class="container header-content">
            <a class="brand" href="{{ route('home') }}">Clínica Vida Salud</a>
            <nav class="site-nav" aria-label="Principal">
                @if (session()->has(\Illuminate\Support\Facades\Auth::guard('web')->getName()))
                    <a href="{{ route('panel') }}">Panel</a>
                    @if (in_array(\App\Modules\Identity\PermissionCatalog::MANAGE_ACCOUNTS, $identityPermissions ?? [], true))
                        <a href="{{ route('accounts.index') }}">Usuarios</a>
                    @endif
                    @if (in_array(\App\Modules\Identity\PermissionCatalog::MANAGE_PATIENTS, $identityPermissions ?? [], true))
                        <a href="{{ route('patients.index') }}">Pacientes</a>
                    @endif
                    @if (in_array(\App\Modules\Identity\PermissionCatalog::MANAGE_PHYSICIANS, $identityPermissions ?? [], true))
                        <a href="{{ route('physicians.index') }}">Médicos</a>
                    @endif
                    @if (in_array(\App\Modules\Identity\PermissionCatalog::MANAGE_SPECIALTIES, $identityPermissions ?? [], true))
                        <a href="{{ route('specialties.index') }}">Especialidades</a>
                    @endif
                    @if (in_array(\App\Modules\Identity\PermissionCatalog::MANAGE_SCHEDULES, $identityPermissions ?? [], true))
                        <a href="{{ route('schedules.index') }}">Horarios</a>
                    @endif
                    @if (in_array(\App\Modules\Identity\PermissionCatalog::RESCHEDULE_APPOINTMENTS, $identityPermissions ?? [], true))
                        <a href="{{ route('appointments.index') }}">Citas</a>
                    @elseif (in_array(\App\Modules\Identity\PermissionCatalog::CONSULT_AVAILABILITY, $identityPermissions ?? [], true))
                        <a href="{{ route('availability.consult') }}">Citas</a>
                    @elseif (in_array(\App\Modules\Identity\PermissionCatalog::BOOK_APPOINTMENTS, $identityPermissions ?? [], true))
                        <a href="{{ route('appointments.create') }}">Citas</a>
                    @endif
                    @if (in_array(\App\Modules\Identity\PermissionCatalog::CONSULT_AGENDA, $identityPermissions ?? [], true)
                        || in_array(\App\Modules\Identity\PermissionCatalog::MARK_ATTENDED, $identityPermissions ?? [], true))
                        <a href="{{ route('appointments.agenda') }}">Agenda</a>
                    @endif
                    @if (in_array(\App\Modules\Identity\PermissionCatalog::CONSULT_HISTORY, $identityPermissions ?? [], true))
                        <a href="{{ route('appointments.history') }}">Historial</a>
                    @endif
                    @if (in_array(\App\Modules\Identity\PermissionCatalog::GENERATE_REPORTS, $identityPermissions ?? [], true))
                        <a href="{{ route('reports.generate') }}">Reportes</a>
                    @endif
                    @if (in_array(\App\Modules\Identity\PermissionCatalog::UPDATE_PROFILE, $identityPermissions ?? [], true))
                        <a href="{{ route('portal.profile') }}">Perfil</a>
                    @endif
                    @if (in_array(\App\Modules\Identity\PermissionCatalog::DECLARE_HEALTH, $identityPermissions ?? [], true))
                        <a href="{{ route('patients.declared') }}">Información médica</a>
                    @endif
                    @if (in_array(\App\Modules\Identity\PermissionCatalog::CONSULT_CLINICAL_HISTORY, $identityPermissions ?? [], true))
                        <a href="{{ route('appointments.clinical-history') }}">Historial médico</a>
                    @endif
                    <form class="inline-form" method="post" action="{{ route('logout', absolute: false) }}">
                        @csrf
                        <button class="text-button" type="submit">Cerrar sesión</button>
                    </form>
                @else
                    @unless (request()->routeIs('login'))
                        <a class="nav-login" href="{{ route('login') }}">Iniciar sesión</a>
                    @endunless
                    @unless (request()->routeIs('portal.register'))
                        <a href="{{ route('portal.register') }}">Crear cuenta</a>
                    @endunless
                @endif
            </nav>
            <span class="status">Sistema web</span>
        </div>
    </header>
    <main id="main-content" class="container" tabindex="-1">
        @yield('content')
    </main>
    <footer class="site-footer container">
        <p>Clínica Vida Salud · Gestión de citas</p>
        <p>Esta página pública no muestra datos de pacientes ni información clínica.</p>
    </footer>
</body>
</html>
