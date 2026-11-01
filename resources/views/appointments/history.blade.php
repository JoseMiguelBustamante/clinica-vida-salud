@extends('layouts.clinic')

@section('title', 'Historial de citas — Clínica Vida Salud')

@section('content')
    <section class="page-head" aria-labelledby="page-title">
        <p class="eyebrow">Citas</p>
        <h1 id="page-title">Citas e historial del paciente</h1>
        <p class="lead">Consulta por paciente y estado. No muestra diagnósticos ni notas médicas. El historial del portal es una consulta distinta.</p>
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

    <form class="search-bar" method="get" action="{{ route('appointments.history', absolute: false) }}">
        <div class="field">
            <label for="q">Buscar paciente por CI, nombres o apellidos</label>
            <input id="q" name="q" type="search" value="{{ $q }}">
        </div>
        @if ($patient)
            <input type="hidden" name="id_paciente" value="{{ $patient->id_paciente }}">
        @endif
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

    @if ($matches->isNotEmpty())
        <div class="table-wrap">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Paciente</th>
                        <th>CI</th>
                        <th>Estado ficha</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($matches as $match)
                        <tr>
                            <td>{{ $match->apellidos }}, {{ $match->nombres }}</td>
                            <td>{{ $match->ci ?: '—' }}</td>
                            <td>{{ $match->activo ? 'Activo' : 'Inactivo' }}</td>
                            <td>
                                <a href="{{ route('appointments.history', ['id_paciente' => $match->id_paciente, 'estado' => $estado]) }}">Ver historial</a>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @elseif ($q !== '')
        <p>No hay pacientes que coincidan con la búsqueda.</p>
    @endif

    @if ($patient)
        <section class="implementation">
            <p>Paciente: {{ $patient->apellidos }}, {{ $patient->nombres }}
                · CI {{ $patient->ci ?: 'no indicado' }}
                · {{ $patient->activo ? 'Activo' : 'Inactivo' }}</p>
        </section>

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
                    </tr>
                </thead>
                <tbody>
                    @forelse ($appointments as $appointment)
                        <tr>
                            <td>{{ \Illuminate\Support\Carbon::parse((string) $appointment->inicio)->timezone('UTC')->format('Y-m-d') }}</td>
                            <td>{{ \Illuminate\Support\Carbon::parse((string) $appointment->inicio)->timezone('UTC')->format('H:i') }}
                                – {{ \Illuminate\Support\Carbon::parse((string) $appointment->fin)->timezone('UTC')->format('H:i') }} UTC</td>
                            <td>{{ $appointment->codigo }}</td>
                            <td>{{ $appointment->medico_apellidos }}, {{ $appointment->medico_nombres }}</td>
                            <td>{{ $appointment->especialidad_nombre }}</td>
                            <td>{{ $appointment->estado }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6">No hay citas que coincidan con los criterios.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    @elseif ($q === '')
        <p>Busque y seleccione un paciente para ver su historial de citas.</p>
    @endif
@endsection
