@php
    $row = $patient ?? null;
@endphp
<div class="field">
    <label for="ci">Documento de identidad (opcional)</label>
    <input id="ci" name="ci" type="text" maxlength="30" value="{{ old('ci', $row->ci ?? '') }}" autocomplete="off">
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
    <label for="fecha_nacimiento">Fecha de nacimiento (opcional)</label>
    <input id="fecha_nacimiento" name="fecha_nacimiento" type="date"
        value="{{ old('fecha_nacimiento', isset($row->fecha_nacimiento) ? \Illuminate\Support\Str::of($row->fecha_nacimiento)->substr(0, 10) : '') }}">
</div>
<div class="field">
    <label for="sexo">Sexo (opcional)</label>
    <select id="sexo" name="sexo">
        @php $sexo = (string) old('sexo', $row->sexo ?? ''); @endphp
        <option value="" @selected($sexo === '')>No indicado</option>
        <option value="Femenino" @selected($sexo === 'Femenino')>Femenino</option>
        <option value="Masculino" @selected($sexo === 'Masculino')>Masculino</option>
        <option value="Otro" @selected($sexo === 'Otro')>Otro</option>
    </select>
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
<div class="field">
    <label for="direccion">Dirección (opcional)</label>
    <input id="direccion" name="direccion" type="text" maxlength="2000"
        value="{{ old('direccion', $row->direccion ?? '') }}" autocomplete="off">
</div>
<div class="field">
    <label for="id_usuario">Cuenta de portal (opcional)</label>
    <select id="id_usuario" name="id_usuario">
        <option value="">Sin vincular</option>
        @foreach ($accounts ?? [] as $account)
            <option value="{{ $account->id_usuario }}"
                @selected((string) old('id_usuario', $row->id_usuario ?? '') === (string) $account->id_usuario)>
                {{ $account->nombre_usuario }}
            </option>
        @endforeach
    </select>
    <p>El vínculo aprobado asocia una cuenta con rol Paciente a esta ficha. El registro del portal no lo crea solo.</p>
</div>
