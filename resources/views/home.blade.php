@extends('layouts.clinic')

@section('title', 'Clínica Vida Salud — Gestión de citas')
@section('main_class', 'landing-main')

@section('content')
    <section class="landing-hero" aria-labelledby="page-title">
        <div class="container landing-hero-grid">
            <div class="landing-copy">
                <p class="landing-kicker">Clínica Vida Salud</p>
                <h1 id="page-title">Gestión de citas médicas</h1>
                <p class="landing-lead">
                    Coordine pacientes, médicos, horarios y citas desde un solo sistema web,
                    de forma rápida, segura y eficiente.
                </p>
                <nav class="landing-actions" aria-label="Acceso">
                    <a class="landing-btn landing-btn-primary" href="{{ route('login') }}">
                        <svg viewBox="0 0 24 24" aria-hidden="true" focusable="false">
                            <path fill="currentColor" d="M12 12a4.5 4.5 0 1 0-4.5-4.5A4.5 4.5 0 0 0 12 12Zm0 2.25c-3.6 0-6.75 1.8-6.75 4v1.25h13.5V18.25c0-2.2-3.15-4-6.75-4Z"/>
                        </svg>
                        Iniciar sesión
                    </a>
                    <a class="landing-btn landing-btn-secondary" href="{{ route('portal.register') }}">
                        <svg viewBox="0 0 24 24" aria-hidden="true" focusable="false">
                            <path fill="currentColor" d="M11 5h2v6h6v2h-6v6h-2v-6H5v-2h6V5Z"/>
                        </svg>
                        Crear cuenta
                    </a>
                </nav>
                <ul class="landing-trust">
                    <li>
                        <span class="landing-trust-icon" aria-hidden="true">
                            <svg viewBox="0 0 24 24"><path fill="currentColor" d="M12 2 4.5 5v6.5c0 4.7 3.2 9 7.5 10.5 4.3-1.5 7.5-5.8 7.5-10.5V5L12 2Zm-1.1 13.2-3.2-3.2 1.4-1.4 1.8 1.8 3.8-3.8 1.4 1.4-5.2 5.2Z"/></svg>
                        </span>
                        Seguro
                    </li>
                    <li>
                        <span class="landing-trust-icon" aria-hidden="true">
                            <svg viewBox="0 0 24 24"><path fill="currentColor" d="M7 3h3l1.2 4.2L15 5.5l1.5 1.5-3.7 2.8L14 14H9.8L8.5 9.8 4.8 12 3.3 10.5l3.9-2.3L7 3Zm8.5 9.5 2.2 1.3L20 12l1.2 1.8-1.8 1.3.4 2.5-2.1.7-1.3-2.1-2.1 1 .7-2.3-1.8-1.3 2.3-.6Z"/></svg>
                        </span>
                        Rápido
                    </li>
                    <li>
                        <span class="landing-trust-icon" aria-hidden="true">
                            <svg viewBox="0 0 24 24"><path fill="currentColor" d="M3 5.5A2.5 2.5 0 0 1 5.5 3h13A2.5 2.5 0 0 1 21 5.5v9A2.5 2.5 0 0 1 18.5 17H13v2h2.5v2h-7v-2H11v-2H5.5A2.5 2.5 0 0 1 3 14.5v-9ZM5.5 5a.5.5 0 0 0-.5.5v9a.5.5 0 0 0 .5.5h13a.5.5 0 0 0 .5-.5v-9a.5.5 0 0 0-.5-.5h-13Z"/></svg>
                        </span>
                        Desde cualquier dispositivo
                    </li>
                </ul>
            </div>

            <div class="landing-visual" aria-hidden="true">
                <div class="landing-photo-frame">
                    <img
                        class="landing-photo"
                        src="/images/landing-doctor.jpg"
                        alt=""
                        width="720"
                        height="960"
                        decoding="async"
                    >
                </div>
                <aside class="landing-mock">
                    <div class="landing-mock-side">
                        <span class="is-active"></span>
                        <span></span>
                        <span></span>
                        <span></span>
                    </div>
                    <div class="landing-mock-body">
                        <div class="landing-mock-head">
                            <strong>Agenda médica</strong>
                            <span>Mar, 28 de enero</span>
                        </div>
                        <ul class="landing-mock-list">
                            <li>
                                <span class="landing-mock-avatar">MT</span>
                                <span>
                                    <strong>María Torres</strong>
                                    <small>Medicina General · 09:00</small>
                                </span>
                                <em class="is-ok">Confirmada</em>
                            </li>
                            <li>
                                <span class="landing-mock-avatar">CM</span>
                                <span>
                                    <strong>Carlos Mendoza</strong>
                                    <small>Cardiología · 10:30</small>
                                </span>
                                <em class="is-wait">Pendiente</em>
                            </li>
                            <li>
                                <span class="landing-mock-avatar">AR</span>
                                <span>
                                    <strong>Ana Ruiz</strong>
                                    <small>Pediatría · 11:15</small>
                                </span>
                                <em class="is-ok">Confirmada</em>
                            </li>
                            <li>
                                <span class="landing-mock-avatar">LG</span>
                                <span>
                                    <strong>Luis García</strong>
                                    <small>Dermatología · 14:00</small>
                                </span>
                                <em class="is-wait">Pendiente</em>
                            </li>
                        </ul>
                    </div>
                </aside>
            </div>
        </div>
    </section>

    <section class="landing-features" id="servicios" aria-labelledby="features-title">
        <div class="container">
            <h2 id="features-title" class="visually-hidden">Servicios del sistema</h2>
            <div class="landing-feature-grid">
                <article class="landing-feature" id="agenda">
                    <span class="landing-feature-icon" aria-hidden="true">
                        <svg viewBox="0 0 24 24"><path fill="currentColor" d="M7 3h2v2h6V3h2v2h3v16H4V5h3V3Zm11 6H6v10h12V9Zm-8 2h2v2H10v-2Zm4 0h2v2h-2v-2Zm-4 4h2v2H10v-2Zm4 0h2v2h-2v-2Z"/></svg>
                    </span>
                    <h3>Agenda de citas</h3>
                    <p>Administre y programe citas médicas de manera sencilla.</p>
                </article>
                <article class="landing-feature" id="pacientes">
                    <span class="landing-feature-icon" aria-hidden="true">
                        <svg viewBox="0 0 24 24"><path fill="currentColor" d="M9 11a3.5 3.5 0 1 0-3.5-3.5A3.5 3.5 0 0 0 9 11Zm6.5-.5a3 3 0 1 0-3-3 3 3 0 0 0 3 3ZM2.5 19.5c0-2.8 2.9-4.5 6.5-4.5s6.5 1.7 6.5 4.5V21H2.5v-1.5Zm12 .2c.5-.1 1-.2 1.5-.2 3.2 0 5.5 1.4 5.5 3V21h-7v-1.3Z"/></svg>
                    </span>
                    <h3>Gestión de pacientes</h3>
                    <p>Consulte y mantenga la información clínica de sus pacientes.</p>
                </article>
                <article class="landing-feature" id="medicos">
                    <span class="landing-feature-icon" aria-hidden="true">
                        <svg viewBox="0 0 24 24"><path fill="currentColor" d="M12 2a3 3 0 0 1 3 3v1h2.5A2.5 2.5 0 0 1 20 8.5V11h-2v7.5A3.5 3.5 0 0 1 14.5 22h-5A3.5 3.5 0 0 1 6 18.5V11H4V8.5A2.5 2.5 0 0 1 6.5 6H9V5a3 3 0 0 1 3-3Zm0 2a1 1 0 0 0-1 1v1h2V5a1 1 0 0 0-1-1Zm-3.5 4A.5.5 0 0 0 8 8.5V11h8V8.5a.5.5 0 0 0-.5-.5h-7ZM8 13v5.5A1.5 1.5 0 0 0 9.5 20h5a1.5 1.5 0 0 0 1.5-1.5V13H8Zm3 1h2v2h2v2h-2v2h-2v-2H9v-2h2v-2Z"/></svg>
                    </span>
                    <h3>Coordinación de médicos</h3>
                    <p>Organice horarios y disponibilidad del equipo médico.</p>
                </article>
            </div>
        </div>
    </section>
@endsection
