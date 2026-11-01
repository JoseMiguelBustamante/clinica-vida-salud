@extends('layouts.clinic')

@section('title', 'Historial médico — Clínica Vida Salud')

@section('content')
    <section class="app-page" aria-labelledby="page-title">
        <p class="app-breadcrumb">Portal / Historial médico</p>
        <h1 id="page-title">Historial médico</h1>
        <p class="lead">Consulta de solo lectura de los registros propios cuya visualización está autorizada. El vínculo con la ficha no abre diagnósticos ni notas de autoría profesional. Esta consulta no es una historia clínica electrónica completa.</p>

        @if (! $enabled)
            <p>El portal no está habilitado.</p>
            <p><a href="{{ route('panel') }}">Volver al panel</a></p>
        @elseif ($queryFailed)
            <p>No fue posible completar la consulta.</p>
            <p><a href="{{ route('panel') }}">Volver al panel</a></p>
        @elseif (! $linked)
            <p>El acceso requiere un vínculo aprobado con el registro de paciente.</p>
            <p><a href="{{ route('panel') }}">Volver al panel</a></p>
        @else
            <p>Paciente: {{ $patient->apellidos }}, {{ $patient->nombres }}</p>
            <div class="list-card">
                <div class="table-wrap">
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>Fecha</th>
                                <th>Horario</th>
                                <th>Código</th>
                                <th>Médico</th>
                                <th>Especialidad</th>
                                <th>Estado</th>
                                <th>Acciones</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($records as $record)
                                <tr>
                                    <td>{{ \Illuminate\Support\Carbon::parse((string) $record->inicio)->timezone('UTC')->format('Y-m-d') }}</td>
                                    <td>{{ \Illuminate\Support\Carbon::parse((string) $record->inicio)->timezone('UTC')->format('H:i') }}
                                        – {{ \Illuminate\Support\Carbon::parse((string) $record->fin)->timezone('UTC')->format('H:i') }} UTC</td>
                                    <td>{{ $record->codigo }}</td>
                                    <td>{{ $record->medico_apellidos }}, {{ $record->medico_nombres }}</td>
                                    <td>{{ $record->especialidad_nombre }}</td>
                                    <td><span class="status-pill">{{ $record->estado }}</span></td>
                                    <td>
                                        <nav class="row-actions">
                                            <a class="row-action" href="{{ route('appointments.clinical-history.show', $record->id_cita) }}">Ver detalle</a>
                                        </nav>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="7">No hay información disponible en el portal. Esto no significa que carezca de antecedentes clínicos en la clínica.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
                <p class="list-meta">{{ $records->count() === 1 ? 'Mostrando 1 registro' : 'Mostrando '.$records->count().' registros' }}</p>
            </div>
            <p><a href="{{ route('panel') }}">Volver al panel</a>
                · <a href="{{ route('patients.declared') }}">Información declarada</a></p>
        @endif
    </section>
@endsection
