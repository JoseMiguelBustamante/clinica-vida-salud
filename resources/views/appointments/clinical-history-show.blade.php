@extends('layouts.clinic')

@section('title', 'Detalle del historial — Clínica Vida Salud')

@section('content')
    <section class="page-head" aria-labelledby="page-title">
        <p class="eyebrow">Portal del paciente</p>
        <h1 id="page-title">Detalle autorizado</h1>
        <p class="lead">Solo se muestran los datos de la cita cuya visualización está autorizada. No incluye diagnósticos ni notas elaboradas por profesionales de salud.</p>
    </section>

    @if (! $enabled)
        <p>El portal no está habilitado.</p>
        <p><a href="{{ route('appointments.clinical-history') }}">Volver al historial</a></p>
    @elseif ($queryFailed)
        <p>No fue posible completar la consulta.</p>
        <p><a href="{{ route('appointments.clinical-history') }}">Volver al historial</a></p>
    @elseif (! $linked)
        <p>El acceso requiere un vínculo aprobado con el registro de paciente.</p>
        <p><a href="{{ route('panel') }}">Volver al panel</a></p>
    @elseif ($unavailable)
        <p>El registro no está disponible para su consulta.</p>
        <p><a href="{{ route('appointments.clinical-history') }}">Volver al historial</a></p>
    @else
        <section class="implementation">
            <p>Paciente: {{ $patient->apellidos }}, {{ $patient->nombres }}</p>
            <p>Código: {{ $record->codigo }}</p>
            <p>Estado: {{ $record->estado }} · Modalidad: {{ $record->modalidad }}</p>
            <p>Fecha: {{ \Illuminate\Support\Carbon::parse((string) $record->inicio)->timezone('UTC')->format('Y-m-d') }}
                · {{ \Illuminate\Support\Carbon::parse((string) $record->inicio)->timezone('UTC')->format('H:i') }}
                – {{ \Illuminate\Support\Carbon::parse((string) $record->fin)->timezone('UTC')->format('H:i') }} UTC</p>
            <p>Médico: {{ $record->medico_apellidos }}, {{ $record->medico_nombres }}
                @if ($record->registro_profesional)
                    · {{ $record->registro_profesional }}
                @endif
            </p>
            <p>Especialidad: {{ $record->especialidad_nombre }}</p>
            <p>Motivo declarado: {{ $record->motivo_consulta ?: '—' }}</p>
            @if ($record->estado === 'Cancelada')
                <p>Motivo de cancelación: {{ $record->motivo_cancelacion ?: '—' }}</p>
            @endif
        </section>
        <p><a href="{{ route('appointments.clinical-history') }}">Volver al historial</a></p>
    @endif
@endsection
