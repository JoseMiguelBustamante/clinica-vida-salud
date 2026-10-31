@php
    $row = $schedule ?? null;
    $start = old('hora_inicio', $row ? substr((string) $row->hora_inicio, 0, 5) : '');
    $end = old('hora_fin', $row ? substr((string) $row->hora_fin, 0, 5) : '');
@endphp
@if (! isset($row))
    <div class="field">
        <label for="id_medico">Médico</label>
        @if ($physicians->isEmpty())
            <p>No hay médicos registrados. Primero registre el personal médico.</p>
        @else
            <select id="id_medico" name="id_medico" required>
                <option value="">Seleccione</option>
                @foreach ($physicians as $physician)
                    <option value="{{ $physician->id_medico }}" @selected((string) old('id_medico') === (string) $physician->id_medico)>
                        {{ $physician->apellidos }}, {{ $physician->nombres }} · {{ $physician->registro_profesional }}
                    </option>
                @endforeach
            </select>
        @endif
    </div>
@endif
<div class="field">
    <label for="dia_semana">Día</label>
    <select id="dia_semana" name="dia_semana" required>
        @foreach ($weekdays as $value => $label)
            <option value="{{ $value }}" @selected((string) old('dia_semana', $row->dia_semana ?? '') === $value)>{{ $label }}</option>
        @endforeach
    </select>
</div>
<div class="field">
    <label for="hora_inicio">Hora de inicio</label>
    <input id="hora_inicio" name="hora_inicio" type="time" required value="{{ $start }}">
</div>
<div class="field">
    <label for="hora_fin">Hora de finalización</label>
    <input id="hora_fin" name="hora_fin" type="time" required value="{{ $end }}">
</div>
<div class="field">
    <label for="duracion_cita_minutos">Duración de cada cita (minutos)</label>
    <input id="duracion_cita_minutos" name="duracion_cita_minutos" type="number" min="1" max="1440" required
        value="{{ old('duracion_cita_minutos', $row->duracion_cita_minutos ?? 30) }}">
</div>
@if (! isset($row))
    <div class="field">
        <label for="activo">Estado</label>
        <select id="activo" name="activo">
            <option value="1" @selected((string) old('activo', '1') === '1')>Activo</option>
            <option value="0" @selected((string) old('activo') === '0')>Inactivo</option>
        </select>
    </div>
@endif
