<aside class="booking-panel" aria-labelledby="booking-title">
    <header class="booking-panel-head">
        <div>
            <h2 id="booking-title">Nueva cita</h2>
            <p>{{ $bookingHeading }}</p>
        </div>
        <a class="booking-close" href="{{ route('availability.consult', $closeQuery, false) }}" aria-label="Cerrar panel">×</a>
    </header>

    @php
        $panelState = array_filter([
            'vista' => $vista,
            'fecha' => $fecha,
            'estado' => $estado,
            'medico' => $medico,
            'especialidad' => $especialidad,
            'nueva' => 1,
            'hora_inicio' => $hora_inicio,
            'hora_fin' => $hora_fin,
            'id_paciente' => $id_paciente,
            'q' => $q,
            'escala' => $escala === 'normal' ? null : $escala,
        ], static fn ($value) => $value !== null && $value !== '');
        $chosenSpecialty = $specialties->first(
            fn ($specialty): bool => (string) $specialty->id_especialidad === (string) $especialidad,
        );
        $chosenPhysician = $physicians->first(
            fn ($physician): bool => (string) $physician->id_medico === (string) old('id_medico', $medico),
        );
        $summaryDate = explode('-', (string) old('fecha', $fecha));
        $summaryDateLabel = count($summaryDate) === 3 ? $summaryDate[2].'/'.$summaryDate[1].'/'.$summaryDate[0] : '';
    @endphp

    <form class="booking-search" method="get" action="{{ route('availability.consult', absolute: false) }}">
        @foreach ($panelState as $name => $value)
            @if ($name !== 'q')
                <input type="hidden" name="{{ $name }}" value="{{ $value }}">
            @endif
        @endforeach
        <div class="field">
            <label for="booking_q">Buscar paciente</label>
            <input id="booking_q" name="q" type="search" value="{{ $q }}" placeholder="Nombre o cédula" autocomplete="off">
        </div>
        <button class="button button-inline" type="submit">Buscar paciente</button>
        <p class="booking-help">Filtra la lista de pacientes registrados por nombre o cédula.</p>
        @if ($canManagePatients)
            <p class="booking-register"><a href="{{ route('patients.create') }}">Registrar paciente</a></p>
        @endif
    </form>

    @if ($q !== '' && $patients->isEmpty())
        <p class="booking-hint is-warn">Ningún paciente coincide con «{{ $q }}».</p>
    @elseif ($selectedPatient)
        <div class="booking-patient is-selected">
            <strong>{{ $selectedPatient->nombres }} {{ $selectedPatient->apellidos }}</strong>
            <span>Paciente registrado</span>
        </div>
    @endif

    <form class="booking-care" method="get" action="{{ route('availability.consult', absolute: false) }}">
        @foreach ($panelState as $name => $value)
            @if ($name !== 'especialidad')
                <input type="hidden" name="{{ $name }}" value="{{ $value }}">
            @endif
        @endforeach
        <h3>Atención</h3>
        <div class="field">
            <label for="booking_especialidad">Especialidad</label>
            <select id="booking_especialidad" name="especialidad">
                <option value="">Seleccione</option>
                @foreach ($specialties as $specialty)
                    <option value="{{ $specialty->id_especialidad }}" @selected((string) $especialidad === (string) $specialty->id_especialidad)>
                        {{ $specialty->nombre }}
                    </option>
                @endforeach
            </select>
        </div>
        <button class="button button-inline" type="submit">Cargar médicos</button>
        <p class="booking-help">Carga los médicos activos de esa especialidad. El paciente y el horario no se pierden.</p>
    </form>

    <form class="booking-slot" method="get" action="{{ route('availability.consult', absolute: false) }}">
        @foreach ($panelState as $name => $value)
            @if (! in_array($name, ['fecha', 'hora_inicio', 'hora_fin'], true))
                <input type="hidden" name="{{ $name }}" value="{{ $value }}">
            @endif
        @endforeach
        <h3>Fecha y hora</h3>
        <div class="booking-when">
            <div class="field">
                <label for="fecha">Fecha</label>
                <input id="fecha" name="fecha" type="date" required value="{{ $fecha }}" min="{{ $today->toDateString() }}" onchange="this.form.submit()">
            </div>
            <div class="field">
                <label for="hora_inicio">Inicio</label>
                <select id="hora_inicio" name="hora_inicio" required onchange="this.form.submit()">
                    <option value="">--:--</option>
                    @foreach ($clockOptions as $clock)
                        @if ($clock !== $clockOptions[array_key_last($clockOptions)])
                            <option value="{{ $clock }}" @selected($hora_inicio === $clock)>{{ $clock }}</option>
                        @endif
                    @endforeach
                </select>
            </div>
            <div class="field">
                <label for="hora_fin">Fin</label>
                <select id="hora_fin" name="hora_fin" required onchange="this.form.submit()">
                    <option value="">--:--</option>
                    @foreach ($clockOptions as $clock)
                        @if ($clock !== $clockOptions[0])
                            <option value="{{ $clock }}" @selected($hora_fin === $clock)>{{ $clock }}</option>
                        @endif
                    @endforeach
                </select>
            </div>
        </div>
        <button class="button button-inline" type="submit">Ver en el calendario</button>
        <p class="booking-help">Mueve el hueco marcado. Si el fin no es posterior al inicio, se ajusta a 30 minutos.</p>
    </form>

    <form class="booking-form" method="post" action="{{ route('appointments.store', absolute: false) }}" novalidate>
        @csrf
        <input type="hidden" name="origen" value="disponibilidad">
        <input type="hidden" name="modalidad" value="Presencial">
        <input type="hidden" name="id_especialidad" value="{{ old('id_especialidad', $especialidad) }}">
        <input type="hidden" name="fecha" value="{{ $fecha }}">
        <input type="hidden" name="hora_inicio" value="{{ $hora_inicio }}">
        <input type="hidden" name="hora_fin" value="{{ $hora_fin }}">

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
            <label for="id_medico">Médico</label>
            @if ($especialidad && $physicians->isEmpty())
                <p class="booking-hint is-warn">{{ $chosenSpecialty ? $chosenSpecialty->nombre : 'Esta especialidad' }} no tiene médicos activos. Pulse Cargar médicos con otra especialidad.</p>
            @endif
            <select id="id_medico" name="id_medico" required>
                <option value="">Seleccione</option>
                @foreach ($physicians as $physician)
                    <option value="{{ $physician->id_medico }}" @selected((string) old('id_medico', $medico) === (string) $physician->id_medico)>
                        {{ $physician->nombres }} {{ $physician->apellidos }}
                    </option>
                @endforeach
            </select>
        </div>

        @if ($slotHint === 'available')
            <p class="booking-hint is-ok">Médico disponible en este horario.</p>
        @elseif ($slotHint === 'occupied')
            <p class="booking-hint is-warn">Ese intervalo ya está ocupado. El sistema lo volverá a comprobar al guardar.</p>
        @elseif ($slotHint === 'need-selection')
            <p class="booking-hint">Elija especialidad y pulse Cargar médicos. El intervalo se comprueba al guardar.</p>
        @elseif ($slotHint === 'need-physician')
            <p class="booking-hint">Elija un médico. El intervalo se comprueba al guardar.</p>
        @elseif ($slotHint === 'unknown')
            <p class="booking-hint">El intervalo se vuelve a comprobar al guardar. Programar no confirma la asistencia.</p>
        @endif

        <div class="field">
            <label for="motivo_consulta">Observaciones (opcional)</label>
            <textarea id="motivo_consulta" name="motivo_consulta" maxlength="2000" rows="3" placeholder="Añadir una nota">{{ old('motivo_consulta') }}</textarea>
        </div>

        @if ($selectedPatient || $chosenPhysician)
            <p class="booking-summary">
                {{ $selectedPatient ? $selectedPatient->nombres.' '.$selectedPatient->apellidos : 'Paciente' }}
                @if ($chosenPhysician)
                    · {{ $chosenPhysician->nombres }} {{ $chosenPhysician->apellidos }}
                @endif
                <span>{{ $summaryDateLabel }}{{ $hora_inicio && $hora_fin ? ' · '.$hora_inicio.' – '.$hora_fin : '' }}</span>
            </p>
        @endif

        <div class="booking-actions">
            <a class="button button-inline booking-cancel" href="{{ route('availability.consult', $closeQuery, false) }}">Cancelar</a>
            <button class="button button-inline" type="submit">Guardar cita</button>
        </div>
    </form>
</aside>
