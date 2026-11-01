@extends('layouts.clinic')

@section('title', 'Clínica Vida Salud — Gestión de citas')

@section('content')
    <section class="intro" aria-labelledby="page-title">
        <p class="eyebrow">Clínica Vida Salud</p>
        <h1 id="page-title">Gestión de citas médicas</h1>
        <p class="lead">Aplicación web para coordinar pacientes, médicos, horarios y citas,
            construida como un monolito modular Laravel con páginas renderizadas en el servidor.</p>
        <a class="section-link" href="#implementation-status">Ver el estado de implementación</a>
    </section>

    <section class="implementation" id="implementation-status" aria-labelledby="status-title">
        <h2 id="status-title">Estado de implementación</h2>
        <p>Los casos de uso CU01 a CU22 están disponibles en esta aplicación.</p>
        <p>Inicio de sesión del personal, cuentas, pacientes, médicos, especialidades, horarios, consulta de disponibilidad, programación, reprogramación, cancelación, confirmación, asistencia, atención, agenda del médico, historial de citas, reportes administrativos, registro del portal, perfil, información médica declarada, historial clínico autorizado y recordatorios de cita están disponibles.</p>
        <h3>Módulos de la aplicación</h3>
        <ul class="module-list">
            <li><strong>Identity</strong> — inicio de sesión, panel, administración de cuentas, registro del portal y preferencias de perfil.</li>
            <li><strong>Patients</strong> — identificación de pacientes, datos de contacto e información médica declarada.</li>
            <li><strong>MedicalStaff</strong> — médicos y catálogo de especialidades.</li>
            <li><strong>Schedules</strong> — horarios semanales y duración de los intervalos.</li>
            <li><strong>Appointments</strong> — disponibilidad, programación, reprogramación, cancelación, confirmación, asistencia, atención, agenda, historial, reportes, historial autorizado del portal y recordatorios.</li>
        </ul>
    </section>
@endsection
