<aside class="booking-panel" aria-labelledby="booking-title">
    <header class="booking-panel-head">
        <div>
            <h2 id="booking-title">Nueva cita</h2>
            <p>{{ $bookingHeading }}</p>
        </div>
        <a class="booking-close" href="{{ route('availability.consult', $closeQuery, false) }}" aria-label="Cerrar panel">×</a>
    </header>

    <form class="booking-search" method="get" action="{{ route('availability.consult', absolute: false) }}">
        <input type="hidden" name="vista" value="{{ $vista }}">
        <input type="hidden" name="fecha" value="{{ $fecha }}">
        <input type="hidden" name="estado" value="{{ $estado }}">
        <input type="hidden" name="medico" value="{{ $medico }}">
        <input type="hidden" name="nueva" value="1">
        <input type="hidden" name="hora_inicio" value="{{ $hora_inicio }}">
        <input type="hidden" name="hora_fin" value="{{ $hora_fin }}">
        <input type="hidden" name="id_paciente" value="{{ $id_paciente }}">
        <div class="field">
            <label for="booking_q">Paciente</label>
            <input id="booking_q" name="q" type="search" value="{{ $q }}" placeholder="Buscar paciente" autocomplete="off">
        </div>
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
        <button class="button button-inline" type="submit">Buscar</button>
        @if ($canManagePatients)
            <p class="booking-register"><a href="{{ route('patients.create') }}">Registrar paciente</a></p>
        @endif
    </form>

    @if ($selectedPatient)
        <div class="booking-patient is-selected">
            <strong>{{ $selectedPatient->nombres }} {{ $selectedPatient->apellidos }}</strong>
            <span>Paciente registrado</span>
        </div>
    @endif

    <form class="booking-form" method="post" action="{{ route('appointments.store', absolute: false) }}" novalidate>
        @csrf
        <input type="hidden" name="origen" value="disponibilidad">
        <input type="hidden" name="modalidad" value="Presencial">

        <div class="field">
            <label for="id_paciente">Seleccione el paciente</label>
            <select id="id_paciente" name="id_paciente" required>
                <option value="">Seleccione</option>
                @foreach ($patients as $patient)
                    <option value="{{ $patient->id_paciente }}" @selected((string) old('id_paciente', $id_paciente) === (string) $patient->id_paciente)>
                        {{ $patient->apellidos }}, {{ $patient->nombres }}{{ $patient->ci ? ' · '.$patient->ci : '' }}
                    </option>
                @endforeach
            </select>
        </div>

        <h3>Atención</h3>
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
                <p>No hay médicos activos para esa especialidad.</p>
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
            <p class="booking-hint">Elija especialidad y médico. El intervalo se comprueba al guardar.</p>
        @elseif ($slotHint === 'unknown')
            <p class="booking-hint">El intervalo se vuelve a comprobar al guardar. Programar no confirma la asistencia.</p>
        @endif

        <h3>Fecha y hora</h3>
        <div class="booking-when">
            <div class="field">
                <label for="fecha">Fecha</label>
                <input id="fecha" name="fecha" type="date" required value="{{ old('fecha', $fecha) }}" min="{{ $today->toDateString() }}">
            </div>
            <div class="field">
                <label for="hora_inicio">Inicio</label>
                <select id="hora_inicio" name="hora_inicio" required>
                    <option value="">--:--</option>
                    @foreach ($clockOptions as $clock)
                        @if ($clock !== $clockOptions[array_key_last($clockOptions)])
                            <option value="{{ $clock }}" @selected((string) old('hora_inicio', $hora_inicio) === $clock)>{{ $clock }}</option>
                        @endif
                    @endforeach
                </select>
            </div>
            <div class="field">
                <label for="hora_fin">Fin</label>
                <select id="hora_fin" name="hora_fin" required>
                    <option value="">--:--</option>
                    @foreach ($clockOptions as $clock)
                        @if ($clock !== $clockOptions[0])
                            <option value="{{ $clock }}" @selected((string) old('hora_fin', $hora_fin) === $clock)>{{ $clock }}</option>
                        @endif
                    @endforeach
                </select>
            </div>
        </div>

        <div class="field">
            <label for="motivo_consulta">Observaciones (opcional)</label>
            <textarea id="motivo_consulta" name="motivo_consulta" maxlength="2000" rows="3" placeholder="Añadir una nota">{{ old('motivo_consulta') }}</textarea>
        </div>

        @if ($selectedPatient || $hora_inicio)
            @php
                $summaryDate = explode('-', (string) old('fecha', $fecha));
                $summaryDateLabel = count($summaryDate) === 3 ? $summaryDate[2].'/'.$summaryDate[1].'/'.$summaryDate[0] : '';
                $chosenPhysician = $physicians->first(
                    fn ($physician): bool => (string) $physician->id_medico === (string) old('id_medico', $medico),
                );
            @endphp
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
