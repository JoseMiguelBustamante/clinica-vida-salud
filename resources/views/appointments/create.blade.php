@extends('layouts.clinic')

@section('title', 'Programar cita — Clínica Vida Salud')

@section('content')
    <section class="app-page" aria-labelledby="page-title">
        <p class="app-breadcrumb">Atención / Citas</p>
        <div class="page-toolbar">
            <div>
                <h1 id="page-title">Programar cita médica</h1>
                <p class="lead">El intervalo se vuelve a comprobar al guardar. Programar no confirma la asistencia.</p>
            </div>
            @if (in_array(\App\Modules\Identity\PermissionCatalog::MANAGE_PATIENTS, $identityPermissions ?? [], true))
                <a class="button button-inline" href="{{ route('patients.create') }}">Registrar paciente</a>
            @endif
        </div>

        @if ($status)
            <p class="form-errors" role="alert">{{ $status }}</p>
        @endif
        @if ($errors->any())
            <div class="form-errors" role="alert"><p>{{ $errors->first() }}</p></div>
        @endif

        <form class="filter-bar" method="get" action="{{ route('appointments.create', absolute: false) }}">
            <div class="field">
                <label for="q">Buscar paciente</label>
                <input id="q" name="q" type="search" value="{{ $q }}">
            </div>
            <div class="field">
                <label for="filtro_especialidad">Cargar médicos de la especialidad</label>
                <select id="filtro_especialidad" name="especialidad">
                    <option value="">Seleccione</option>
                    @foreach ($specialties as $specialty)
                        <option value="{{ $specialty->id_especialidad }}" @selected((string) $especialidad === (string) $specialty->id_especialidad)>
                            {{ $specialty->nombre }}
                        </option>
                    @endforeach
                </select>
            </div>
            <input type="hidden" name="id_paciente" value="{{ $id_paciente }}">
            <input type="hidden" name="medico" value="{{ $medico }}">
            <input type="hidden" name="fecha" value="{{ $fecha }}">
            <input type="hidden" name="hora_inicio" value="{{ $hora_inicio }}">
            <input type="hidden" name="hora_fin" value="{{ $hora_fin }}">
            <button class="button" type="submit">Actualizar listados</button>
        </form>

        <form class="page-form" method="post" action="{{ route('appointments.store', absolute: false) }}" novalidate>
            @csrf
            <div class="field">
                <label for="id_paciente">Paciente</label>
                <select id="id_paciente" name="id_paciente" required>
                    <option value="">Seleccione</option>
                    @foreach ($patients as $patient)
                        <option value="{{ $patient->id_paciente }}" @selected((string) old('id_paciente', $id_paciente) === (string) $patient->id_paciente)>
                            {{ $patient->apellidos }}, {{ $patient->nombres }}{{ $patient->ci ? ' · '.$patient->ci : '' }}
                        </option>
                    @endforeach
                </select>
            </div>
            <div class="field">
                <label for="id_especialidad">Especialidad</label>
                <select id="id_especialidad" name="id_especialidad" required>
                    <option value="">Seleccione</option>
                    @foreach ($specialties as $specialty)
                        <option value="{{ $specialty->id_especialidad }}" @selected((string) old('id_especialidad', $especialidad) === (string) $specialty->id_especialidad)>
                            {{ $specialty->nombre }}
                        </option>
                    @endforeach
                </select>
            </div>
            <div class="field">
                <label for="id_medico">Médico</label>
                @if ($especialidad && $physicians->isEmpty())
                    <p>No hay médicos activos para esa especialidad. Consulte otra o vuelva a la disponibilidad.</p>
                @endif
                <select id="id_medico" name="id_medico" required>
                    <option value="">Seleccione</option>
                    @foreach ($physicians as $physician)
                        <option value="{{ $physician->id_medico }}" @selected((string) old('id_medico', $medico) === (string) $physician->id_medico)>
                            {{ $physician->apellidos }}, {{ $physician->nombres }} · {{ $physician->registro_profesional }}
                        </option>
                    @endforeach
                </select>
            </div>
            <div class="field">
                <label for="fecha">Fecha</label>
                <input id="fecha" name="fecha" type="date" required value="{{ old('fecha', $fecha) }}" min="{{ now()->toDateString() }}">
            </div>
            <div class="field">
                <label for="hora_inicio">Hora de inicio</label>
                <input id="hora_inicio" name="hora_inicio" type="time" required value="{{ old('hora_inicio', $hora_inicio) }}">
            </div>
            <div class="field">
                <label for="hora_fin">Hora de fin</label>
                <input id="hora_fin" name="hora_fin" type="time" required value="{{ old('hora_fin', $hora_fin) }}">
            </div>
            <div class="field">
                <label for="modalidad">Modalidad</label>
                @php $modalidad = (string) old('modalidad', 'Presencial'); @endphp
                <select id="modalidad" name="modalidad" required>
                    <option value="Presencial" @selected($modalidad === 'Presencial')>Presencial</option>
                    <option value="Virtual" @selected($modalidad === 'Virtual')>Virtual</option>
                </select>
            </div>
            <div class="field">
                <label for="motivo_consulta">Motivo breve (opcional)</label>
                <textarea id="motivo_consulta" name="motivo_consulta" maxlength="2000" rows="3">{{ old('motivo_consulta') }}</textarea>
            </div>
            <button class="button" type="submit">Confirmar programación</button>
            <p><a href="{{ route('availability.consult') }}">Cancelar</a></p>
        </form>
    </section>
@endsection
