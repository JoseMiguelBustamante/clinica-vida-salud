@extends('layouts.clinic')

@section('title', 'Panel — Clínica Vida Salud')

@section('content')
    <section class="auth-panel" aria-labelledby="page-title">
        <h1 id="page-title">Panel de acceso</h1>
        <p class="lead">Sesión activa para {{ $user['username'] }}
            @if ($user['role']['name'] ?? null)
                · {{ $user['role']['name'] }}
            @endif
        </p>

        <nav class="role-menu" aria-label="Módulos según el rol">
            <a href="{{ route('home') }}">Inicio</a>
            <a href="{{ route('panel') }}" aria-current="page">Panel</a>
            <span class="pending">Pacientes, personal, horarios y citas siguen pendientes.</span>
        </nav>

        @if ($user['permissions'] !== [])
            <h2>Permisos del rol</h2>
            <ul class="module-list">
                @foreach ($user['permissions'] as $permission)
                    <li><code>{{ $permission }}</code></li>
                @endforeach
            </ul>
        @else
            <p>Este rol no tiene permisos clínicos asignados.</p>
        @endif
    </section>
@endsection
