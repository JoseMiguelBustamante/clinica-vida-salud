<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="description" content="Clínica Vida Salud application foundation and implementation status.">
    <title>@yield('title', 'Clínica Vida Salud')</title>
    <link rel="stylesheet" href="{{ asset('css/clinic.css') }}?v={{ filemtime(public_path('css/clinic.css')) }}">
</head>
<body class="@yield('body_class')">
    <a class="skip-link" href="#main-content">Skip to main content</a>
    <header class="site-header">
        <div class="container header-content">
            <a class="brand" href="{{ route('home') }}">Clínica Vida Salud</a>
            <nav class="site-nav" aria-label="Principal">
                @if (session()->has(\Illuminate\Support\Facades\Auth::guard('web')->getName()))
                    <a href="{{ route('panel') }}">Panel</a>
                    <form class="inline-form" method="post" action="{{ route('logout') }}">
                        @csrf
                        <button class="text-button" type="submit">Cerrar sesión</button>
                    </form>
                @elseif (! request()->routeIs('login'))
                    <a class="nav-login" href="{{ route('login') }}">Iniciar sesión</a>
                @endif
            </nav>
            <span class="status">Foundation in progress</span>
        </div>
    </header>
    <main id="main-content" class="container" tabindex="-1">
        @yield('content')
    </main>
    <footer class="site-footer container">
        <p>Clínica Vida Salud · Application bootstrap</p>
        <p>No patient or medical information is displayed on this page.</p>
    </footer>
</body>
</html>
