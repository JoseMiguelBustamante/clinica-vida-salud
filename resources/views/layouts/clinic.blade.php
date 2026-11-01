<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="description" content="Clínica Vida Salud, gestión de citas médicas.">
    <title>@yield('title', 'Clínica Vida Salud')</title>
    <link rel="stylesheet" href="/css/clinic.css?v={{ filemtime(public_path('css/clinic.css')) }}">
</head>
@php
    $useAppShell = session()->has(\Illuminate\Support\Facades\Auth::guard('web')->getName())
        && isset($identityPermissions);
    $identityUser = request()->attributes->get('identity');
@endphp
<body class="@yield('body_class'){{ $useAppShell ? ' is-app' : '' }}">
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
            <footer class="site-footer container">
                <p>Clínica Vida Salud · Gestión de citas</p>
                <p>Esta página pública no muestra datos de pacientes ni información clínica.</p>
            </footer>
        @endif
    @endif
</body>
</html>
