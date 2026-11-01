@extends('layouts.clinic')

@section('title', 'Agenda — Clínica Vida Salud')

@section('content')
    <section class="page-head" aria-labelledby="page-title">
        <p class="eyebrow">Citas</p>
        <h1 id="page-title">Agenda médica</h1>
        <p class="lead">Consulta por médico y fecha. No modifica citas. El médico solo ve su propia agenda.</p>
    </section>

    @if (session('status'))
        <p class="form-errors" role="status">{{ session('status') }}</p>
    @endif
    @if ($errors->any())
        <div class="form-errors" role="alert"><p>{{ $errors->first() }}</p></div>
    @endif
    @if ($status)
        <p class="form-errors" role="status">{{ $status }}</p>
    @endif

    <form class="search-bar" method="get" action="{{ route('appointments.agenda', absolute: false) }}">
        @if ($canSelectPhysician)
            <div class="field">
                <label for="id_medico">Médico</label>
                <select id="id_medico" name="id_medico">
                    <option value="">Seleccione</option>
                    @foreach ($physicians as $physician)
                        <option value="{{ $physician->id_medico }}" @selected((string) $physician_id === (string) $physician->id_medico)>
                            {{ $physician->apellidos }}, {{ $physician->nombres }} · {{ $physician->registro_profesional }}
                        </option>
                    @endforeach
                </select>
            </div>
        @endif
        <div class="field">
            <label for="fecha">Fecha</label>
            <input id="fecha" name="fecha" type="date" value="{{ $fecha }}">
        </div>
        <div class="field">
            <label for="estado">Estado (opcional)</label>
            <select id="estado" name="estado">
                <option value="">Todos</option>
                @foreach (['Programada', 'Confirmada', 'Atendida', 'Cancelada', 'Inasistencia'] as $option)
                    <option value="{{ $option }}" @selected($estado === $option)>{{ $option }}</option>
                @endforeach
            </select>
        </div>
        <button class="button button-inline" type="submit">Consultar</button>
    </form>

    @if ($appointments === null)
        <p>Seleccione la fecha{{ $canSelectPhysician ? ' y el médico' : '' }} para ver la agenda.</p>
    @else
        <div class="table-wrap">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Horario</th>
                        <th>Código</th>
                        <th>Paciente</th>
                        <th>Especialidad</th>
                        <th>Estado</th>
                        @if ($canViewReason)
                            <th>Motivo</th>
                        @endif
                        <th>Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($appointments as $appointment)
                        <tr>
                            <td>{{ \Illuminate\Support\Carbon::parse((string) $appointment->inicio)->timezone('UTC')->format('H:i') }}
                                – {{ \Illuminate\Support\Carbon::parse((string) $appointment->fin)->timezone('UTC')->format('H:i') }} UTC</td>
                            <td>{{ $appointment->codigo }}</td>
                            <td>{{ $appointment->paciente_apellidos }}, {{ $appointment->paciente_nombres }}</td>
                            <td>{{ $appointment->especialidad_nombre }}</td>
                            <td>{{ $appointment->estado }}</td>
                            @if ($canViewReason)
                                <td>{{ $appointment->motivo_consulta ?: '—' }}</td>
                            @endif
                            <td>
                                <a href="{{ route('appointments.show', $appointment->id_cita) }}">Ver</a>
                                @if ($canMarkAttended && in_array($appointment->estado, ['Programada', 'Confirmada'], true))
                                    · <a href="{{ route('appointments.attend', $appointment->id_cita) }}">Marcar como atendida</a>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="{{ $canViewReason ? 7 : 6 }}">No hay citas registradas para esa consulta.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    @endif
@endsection
