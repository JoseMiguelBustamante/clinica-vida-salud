@extends('layouts.clinic')

@section('title', 'Agenda — Clínica Vida Salud')

@section('content')
    <section class="page-head" aria-labelledby="page-title">
        <p class="eyebrow">Citas</p>
        <h1 id="page-title">Agenda médica</h1>
        <p class="lead">Solo las citas de su registro profesional. Marcar como atendida no es llegada ni confirmación.</p>
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
        <div class="field">
            <label for="q">Buscar por código o paciente</label>
            <input id="q" name="q" type="search" value="{{ $q }}">
        </div>
        <button class="button button-inline" type="submit">Buscar</button>
    </form>

    <div class="table-wrap">
        <table class="data-table">
            <thead>
                <tr>
                    <th>Código</th>
                    <th>Paciente</th>
                    <th>Especialidad</th>
                    <th>Inicio</th>
                    <th>Estado</th>
                    <th>Acciones</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($appointments as $appointment)
                    <tr>
                        <td>{{ $appointment->codigo }}</td>
                        <td>{{ $appointment->paciente_apellidos }}, {{ $appointment->paciente_nombres }}</td>
                        <td>{{ $appointment->especialidad_nombre }}</td>
                        <td>{{ \Illuminate\Support\Carbon::parse((string) $appointment->inicio)->timezone('UTC')->format('Y-m-d H:i') }}</td>
                        <td>{{ $appointment->estado }}</td>
                        <td>
                            <a href="{{ route('appointments.show', $appointment->id_cita) }}">Ver</a>
                            @if (in_array($appointment->estado, ['Programada', 'Confirmada'], true))
                                · <a href="{{ route('appointments.attend', $appointment->id_cita) }}">Marcar como atendida</a>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="6">No hay citas en su agenda que coincidan con la búsqueda.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
@endsection
