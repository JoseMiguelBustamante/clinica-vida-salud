<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="description" content="Clínica Vida Salud, gestión de citas médicas.">
    <title>@yield('title', 'Clínica Vida Salud')</title>
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=plus-jakarta-sans:400,500,600,700,800" rel="stylesheet">
    <link rel="stylesheet" href="/css/clinic.css?v={{ filemtime(public_path('css/clinic.css')) }}">
</head>
@php
    $useAppShell = session()->has(\Illuminate\Support\Facades\Auth::guard('web')->getName())
        && isset($identityPermissions);
    $identityUser = request()->attributes->get('identity');
    $isLanding = request()->routeIs('home') && ! $useAppShell;
@endphp
<body class="@yield('body_class'){{ $useAppShell ? ' is-app' : '' }}{{ $isLanding ? ' is-landing' : '' }}">
    <a class="skip-link" href="#main-content">Ir al contenido principal</a>
    @hasSection('hide_chrome')
    @else
        @if ($useAppShell)
            <div class="app-shell">
                @include('layouts.partials.app-sidebar')
                <div class="app-frame">
                    <header class="app-topbar">
                        <p class="app-topbar-title">Gestión de citas médicas</p>
                        <p class="app-topbar-user">{{ $identityUser?->nombre_usuario ?? 'Sesión activa' }}</p>
                    </header>
        @elseif ($isLanding)
            <header class="landing-header">
                <div class="container landing-header-inner">
                    <a class="landing-brand" href="{{ route('home') }}">
                        <span class="landing-brand-mark" aria-hidden="true">
                            <svg viewBox="0 0 32 32" focusable="false">
                                <path fill="currentColor" d="M16 27s-9.5-6.1-12.4-11A6.8 6.8 0 0 1 16 8.2 6.8 6.8 0 0 1 28.4 16C25.5 20.9 16 27 16 27Z"/>
                                <path fill="#fff" d="M7.5 15.2h4.2l1.5-3.4 2.2 7.1 1.6-3.7h7.5v1.8h-6.2l-1.4 3.2-2.3-7.2-1.7 4h-5.4z"/>
                            </svg>
                        </span>
                        <span>Clínica Vida Salud</span>
                    </a>
                    <nav class="landing-nav" aria-label="Principal">
                        <a href="{{ route('home') }}">Inicio</a>
                        <a href="#agenda">Citas</a>
                        <a href="#pacientes">Pacientes</a>
                        <a href="#medicos">Médicos</a>
                    </nav>
                    <div class="landing-header-actions">
                        <a class="landing-header-login" href="{{ route('login') }}">Iniciar sesión</a>
                        <a class="landing-header-register" href="{{ route('portal.register') }}">Crear cuenta</a>
                        <span class="landing-chip">
                            <svg viewBox="0 0 24 24" aria-hidden="true" focusable="false">
                                <path fill="currentColor" d="M4 5.5A1.5 1.5 0 0 1 5.5 4h13A1.5 1.5 0 0 1 20 5.5v9A1.5 1.5 0 0 1 18.5 16H13v2.5h2V20H9v-1.5h2V16H5.5A1.5 1.5 0 0 1 4 14.5v-9Z"/>
                            </svg>
                            Sistema web
                        </span>
                    </div>
                </div>
            </header>
        @else
            <header class="site-header">
                <div class="container header-content">
                    <a class="brand" href="{{ route('home') }}">Clínica Vida Salud</a>
                    <nav class="site-nav" aria-label="Principal">
                        @if (session()->has(\Illuminate\Support\Facades\Auth::guard('web')->getName()))
                            <a href="{{ route('panel') }}">Panel</a>
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
        @endif
    @endif
    <main id="main-content" class="@yield('main_class', $useAppShell ? 'app-content' : 'container')" tabindex="-1">
        @yield('content')
    </main>
    @hasSection('hide_chrome')
    @else
        @if ($useAppShell)
                </div>
            </div>
        @else
            <footer class="site-footer{{ $isLanding ? ' landing-footer' : '' }} container">
                <p>Clínica Vida Salud · Gestión de citas</p>
                <p>Esta página pública no muestra datos de pacientes ni información clínica.</p>
            </footer>
        @endif
    @endif
</body>
</html>
