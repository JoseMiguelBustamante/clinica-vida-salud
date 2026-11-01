@extends('layouts.clinic')

@section('title', 'Clínica Vida Salud — Application foundation')

@section('content')
    <section class="intro" aria-labelledby="page-title">
        <p class="eyebrow">Clínica Vida Salud</p>
        <h1 id="page-title">Building the foundation for coordinated care.</h1>
        <p class="lead">This is the first step toward a clinic management application,
            built as a modular Laravel monolith with server-rendered pages.</p>
        <a class="section-link" href="#implementation-status">View implementation status</a>
    </section>

    <section class="implementation" id="implementation-status" aria-labelledby="status-title">
        <h2 id="status-title">Implementation status</h2>
        <p>The public landing page and modular route registration are in place.
            Clinical workflows are still pending implementation.</p>
            <p>Staff sign-in, accounts, patients, physicians, specialties, schedules, availability lookup, booking, rescheduling, cancellation, confirmation, attendance, marking visits as attended and physician agenda lookup are available.</p>
        <h3>Planned module boundaries</h3>
        <ul class="module-list">
            <li><strong>Identity</strong> — staff sign-in, panel and account administration.</li>
            <li><strong>Patients</strong> — patient identification and contact records.</li>
            <li><strong>MedicalStaff</strong> — physicians and specialty catalog.</li>
            <li><strong>Schedules</strong> — physician weekly hours and slot duration.</li>
            <li><strong>Appointments</strong> — availability, booking, rescheduling, cancellation, confirmation, attendance, marking attended and physician agenda.</li>
        </ul>
    </section>
@endsection
