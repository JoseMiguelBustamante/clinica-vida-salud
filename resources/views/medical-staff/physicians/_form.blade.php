@php
    $row = $physician ?? null;
    $selected = collect(old('especialidades', $row->id_especialidades ?? []))->map(fn ($id) => (string) $id);
@endphp
<div class="field">
    <label for="registro_profesional">Registro profesional</label>
    <input id="registro_profesional" name="registro_profesional" type="text" required maxlength="50"
        value="{{ old('registro_profesional', $row->registro_profesional ?? '') }}" autocomplete="off">
</div>
<div class="field">
    <label for="nombres">Nombres</label>
    <input id="nombres" name="nombres" type="text" required maxlength="100"
        value="{{ old('nombres', $row->nombres ?? '') }}" autocomplete="off">
</div>
<div class="field">
    <label for="apellidos">Apellidos</label>
    <input id="apellidos" name="apellidos" type="text" required maxlength="100"
        value="{{ old('apellidos', $row->apellidos ?? '') }}" autocomplete="off">
</div>
<div class="field">
    <label for="telefono">Teléfono (opcional)</label>
    <input id="telefono" name="telefono" type="text" maxlength="30"
        value="{{ old('telefono', $row->telefono ?? '') }}" autocomplete="off">
</div>
<div class="field">
    <label for="correo_contacto">Correo de contacto (opcional)</label>
    <input id="correo_contacto" name="correo_contacto" type="text" maxlength="255"
        value="{{ old('correo_contacto', $row->correo_contacto ?? '') }}" autocomplete="off">
</div>
<fieldset class="field">
    <legend>Especialidades</legend>
    @if ($specialties->isEmpty())
        <p>No hay especialidades registradas. Primero registre el catálogo.</p>
    @else
        <div class="choice-list">
            @foreach ($specialties as $specialty)
                @php
                    $id = (string) $specialty->id_especialidad;
                    $already = $selected->contains($id);
                    $disabled = ! $specialty->activo && ! $already;
                @endphp
                <label>
                    <input type="checkbox" name="especialidades[]" value="{{ $specialty->id_especialidad }}"
                        @checked($already) @disabled($disabled)>
                    {{ $specialty->nombre }}
                    @unless ($specialty->activo)
                        (inactiva)
                    @endunless
                </label>
            @endforeach
        </div>
    @endif
</fieldset>
<div class="field">
    <label for="activo">Estado</label>
    <select id="activo" name="activo">
        <option value="1" @selected((string) old('activo', isset($row) ? ($row->activo ? '1' : '0') : '1') === '1')>Activo</option>
        <option value="0" @selected((string) old('activo', isset($row) ? ($row->activo ? '1' : '0') : '1') === '0')>Inactivo</option>
    </select>
</div>
