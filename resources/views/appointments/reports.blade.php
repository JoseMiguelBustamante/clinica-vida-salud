@extends('layouts.clinic')

@section('title', 'Reportes administrativos — Clínica Vida Salud')

@section('content')
    <section class="page-head" aria-labelledby="page-title">
        <p class="eyebrow">Consultas</p>
        <h1 id="page-title">Reportes administrativos</h1>
        <p class="lead">Consulta por fecha, médico, especialidad y estado. No modifica registros ni muestra diagnósticos o notas médicas. La exportación no está habilitada.</p>
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

    @php
        $selectedType = old('tipo', $type);
        $selectedFecha = old('fecha', $fecha);
        $selectedFechaInicio = old('fecha_inicio', $fecha_inicio);
        $selectedFechaFin = old('fecha_fin', $fecha_fin);
        $selectedMedico = old('id_medico', $id_medico);
        $selectedEspecialidad = old('id_especialidad', $id_especialidad);
        $selectedEstado = old('estado', $estado);
    @endphp
    <form class="search-bar" method="get" action="{{ route('reports.generate', absolute: false) }}">
        <div class="field">
            <label for="tipo">Tipo de reporte</label>
            <select id="tipo" name="tipo">
                <option value="">Seleccione</option>
                @foreach ($types as $code => $label)
                    <option value="{{ $code }}" @selected($selectedType === $code)>{{ $label }}</option>
                @endforeach
            </select>
        </div>
        @if (in_array($selectedType, ['citas_fecha', 'agenda_diaria'], true))
            <div class="field">
                <label for="fecha">Fecha</label>
                <input id="fecha" name="fecha" type="date" value="{{ $selectedFecha }}">
            </div>
        @endif
        @if (in_array($selectedType, ['citas_medico', 'citas_especialidad', 'citas_estado', 'totales_periodo'], true))
            <div class="field">
                <label for="fecha_inicio">Fecha inicial</label>
                <input id="fecha_inicio" name="fecha_inicio" type="date" value="{{ $selectedFechaInicio }}">
            </div>
            <div class="field">
                <label for="fecha_fin">Fecha final</label>
                <input id="fecha_fin" name="fecha_fin" type="date" value="{{ $selectedFechaFin }}">
            </div>
        @endif
        @if ($selectedType === 'citas_medico')
            <div class="field">
                <label for="id_medico">Médico</label>
                <select id="id_medico" name="id_medico">
                    <option value="">Seleccione</option>
                    @foreach ($physicians as $physician)
                        <option value="{{ $physician->id_medico }}" @selected((string) $selectedMedico === (string) $physician->id_medico)>
                            {{ $physician->apellidos }}, {{ $physician->nombres }} · {{ $physician->registro_profesional }}
                        </option>
                    @endforeach
                </select>
            </div>
        @endif
        @if ($selectedType === 'citas_especialidad')
            <div class="field">
                <label for="id_especialidad">Especialidad</label>
                <select id="id_especialidad" name="id_especialidad">
                    <option value="">Seleccione</option>
                    @foreach ($specialties as $specialty)
                        <option value="{{ $specialty->id_especialidad }}" @selected((string) $selectedEspecialidad === (string) $specialty->id_especialidad)>
                            {{ $specialty->nombre }}
                        </option>
                    @endforeach
                </select>
            </div>
        @endif
        @if ($selectedType === 'citas_estado')
            <div class="field">
                <label for="estado">Estado</label>
                <select id="estado" name="estado">
                    <option value="">Seleccione</option>
                    @foreach (['Programada', 'Confirmada', 'Atendida', 'Cancelada', 'Inasistencia'] as $option)
                        <option value="{{ $option }}" @selected($selectedEstado === $option)>{{ $option }}</option>
                    @endforeach
                </select>
            </div>
        @endif
        <button class="button button-inline" type="submit" name="generar" value="1">Generar reporte</button>
    </form>

    @if ($generated)
        <section class="implementation" aria-labelledby="filters-title">
            <h2 id="filters-title">Criterios aplicados</h2>
            <p>{{ $types[$type] ?? $type }}
                @if ($fecha !== '')
                    · Fecha {{ $fecha }}
                @endif
                @if ($fecha_inicio !== '' && $fecha_fin !== '')
                    · Período {{ $fecha_inicio }} a {{ $fecha_fin }}
                @endif
                @if ($estado !== '')
                    · Estado {{ $estado }}
                @endif
            </p>
            @if ($totals)
                <p>
                    @if (isset($totals['citas']))
                        Total de citas: {{ $totals['citas'] }}
                    @endif
                    @if (isset($totals['medicos']))
                        · Médicos: {{ $totals['medicos'] }}
                    @endif
                    @if (isset($totals['pacientes']))
                        Pacientes: {{ $totals['pacientes'] }}
                        · Activos: {{ $totals['activos'] }}
                        · Inactivos: {{ $totals['inactivos'] }}
                    @endif
                    @if (isset($totals['Atendida']))
                        Atendidas: {{ $totals['Atendida'] }}
                        · Canceladas: {{ $totals['Cancelada'] }}
                        · Inasistencias: {{ $totals['Inasistencia'] }}
                    @endif
                </p>
            @endif
        </section>

        @if ($type === 'agenda_diaria')
            @forelse ($groups as $group)
                <h3>{{ $group->physician->apellidos }}, {{ $group->physician->nombres }}</h3>
                <div class="table-wrap">
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>Horario</th>
                                <th>Código</th>
                                <th>Paciente</th>
                                <th>Especialidad</th>
                                <th>Estado</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($group->appointments as $appointment)
                                <tr>
                                    <td>{{ \Illuminate\Support\Carbon::parse((string) $appointment->inicio)->timezone('UTC')->format('H:i') }}
                                        – {{ \Illuminate\Support\Carbon::parse((string) $appointment->fin)->timezone('UTC')->format('H:i') }} UTC</td>
                                    <td>{{ $appointment->codigo }}</td>
                                    <td>{{ $appointment->paciente_apellidos }}, {{ $appointment->paciente_nombres }}</td>
                                    <td>{{ $appointment->especialidad_nombre }}</td>
                                    <td>{{ $appointment->estado }}</td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="5">No hay citas registradas para esa consulta.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            @empty
                <p>No hay datos para la consulta.</p>
            @endforelse
        @elseif ($type === 'pacientes')
            <div class="table-wrap">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>CI</th>
                            <th>Nombres</th>
                            <th>Apellidos</th>
                            <th>Teléfono</th>
                            <th>Correo</th>
                            <th>Estado</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($rows as $patient)
                            <tr>
                                <td>{{ $patient->ci ?: '—' }}</td>
                                <td>{{ $patient->nombres }}</td>
                                <td>{{ $patient->apellidos }}</td>
                                <td>{{ $patient->telefono ?: '—' }}</td>
                                <td>{{ $patient->correo_contacto ?: '—' }}</td>
                                <td>{{ $patient->activo ? 'Activo' : 'Inactivo' }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6">No hay datos para la consulta.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        @elseif ($type === 'totales_periodo')
            @if (($totals['Atendida'] ?? 0) === 0 && ($totals['Cancelada'] ?? 0) === 0 && ($totals['Inasistencia'] ?? 0) === 0)
                <p>No hay datos para la consulta.</p>
            @endif
        @else
            <div class="table-wrap">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Fecha</th>
                            <th>Horario</th>
                            <th>Código</th>
                            <th>Paciente</th>
                            <th>Médico</th>
                            <th>Especialidad</th>
                            <th>Estado</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($rows as $appointment)
                            <tr>
                                <td>{{ \Illuminate\Support\Carbon::parse((string) $appointment->inicio)->timezone('UTC')->format('Y-m-d') }}</td>
                                <td>{{ \Illuminate\Support\Carbon::parse((string) $appointment->inicio)->timezone('UTC')->format('H:i') }}
                                    – {{ \Illuminate\Support\Carbon::parse((string) $appointment->fin)->timezone('UTC')->format('H:i') }} UTC</td>
                                <td>{{ $appointment->codigo }}</td>
                                <td>{{ $appointment->paciente_apellidos }}, {{ $appointment->paciente_nombres }}</td>
                                <td>{{ $appointment->medico_apellidos }}, {{ $appointment->medico_nombres }}</td>
                                <td>{{ $appointment->especialidad_nombre }}</td>
                                <td>{{ $appointment->estado }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="7">No hay datos para la consulta.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        @endif
    @elseif ($selectedType === '')
        <p>Seleccione un tipo de reporte e indique los filtros correspondientes.</p>
    @else
        <p>Indique los filtros del reporte seleccionado y pulse Generar reporte.</p>
    @endif
@endsection
