@extends('layouts.clinic')

@section('title', 'Información médica — Clínica Vida Salud')

@section('content')
    <section class="page-split" aria-labelledby="page-title">
        <div class="page-copy">
            <p class="eyebrow">Portal del paciente</p>
            <h1 id="page-title">Información médica declarada</h1>
            <p class="lead">Consulte o actualice los datos que usted declara. Esta validación de formato no acredita veracidad clínica ni sustituye una evaluación profesional. No modifica diagnósticos, notas médicas ni otros registros de autoría profesional.</p>
        </div>

        <div class="page-form">
            @if (session('status') || $status)
                <p class="form-errors" role="status">{{ session('status') ?: $status }}</p>
            @endif
            @if ($errors->any())
                <div class="form-errors" role="alert"><p>{{ $errors->first() }}</p></div>
            @endif

            @if (! $enabled)
                <p>El portal no está habilitado.</p>
                <p><a href="{{ route('panel') }}">Volver al panel</a></p>
            @elseif ($queryFailed)
                <p>No fue posible completar la consulta.</p>
                <p><a href="{{ route('panel') }}">Volver al panel</a></p>
            @elseif (! $linked)
                <p>El acceso requiere un vínculo aprobado con el registro de paciente.</p>
                <p><a href="{{ route('panel') }}">Volver al panel</a></p>
            @else
                <p>Paciente: {{ $patient->apellidos }}, {{ $patient->nombres }}</p>
                @if ($information === null)
                    <p>No hay información declarada todavía. Complete los campos disponibles.</p>
                @endif
                <form class="auth-form" method="post" action="{{ route('patients.declared.update', absolute: false) }}" novalidate>
                    @csrf
                    <div class="field">
                        <label for="grupo_sanguineo">Grupo sanguíneo (opcional)</label>
                        <select id="grupo_sanguineo" name="grupo_sanguineo">
                            @php $group = (string) old('grupo_sanguineo', $information->grupo_sanguineo ?? ''); @endphp
                            <option value="" @selected($group === '')>No indicado</option>
                            @foreach ($bloodGroups as $option)
                                <option value="{{ $option }}" @selected($group === $option)>{{ $option }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="field">
                        <label for="situacion_alergias">Situación de alergias (opcional)</label>
                        <input id="situacion_alergias" name="situacion_alergias" type="text" maxlength="50"
                            value="{{ old('situacion_alergias', $information->situacion_alergias ?? '') }}">
                    </div>
                    <div class="field">
                        <label for="detalle_alergias">Detalle de alergias (opcional)</label>
                        <textarea id="detalle_alergias" name="detalle_alergias" maxlength="2000" rows="3">{{ old('detalle_alergias', $information->detalle_alergias ?? '') }}</textarea>
                    </div>
                    <div class="field">
                        <label for="situacion_enfermedades">Situación de enfermedades (opcional)</label>
                        <input id="situacion_enfermedades" name="situacion_enfermedades" type="text" maxlength="50"
                            value="{{ old('situacion_enfermedades', $information->situacion_enfermedades ?? '') }}">
                    </div>
                    <div class="field">
                        <label for="detalle_enfermedades">Detalle de enfermedades (opcional)</label>
                        <textarea id="detalle_enfermedades" name="detalle_enfermedades" maxlength="2000" rows="3">{{ old('detalle_enfermedades', $information->detalle_enfermedades ?? '') }}</textarea>
                    </div>
                    <div class="field">
                        <label for="situacion_medicamentos">Situación de medicamentos (opcional)</label>
                        <input id="situacion_medicamentos" name="situacion_medicamentos" type="text" maxlength="50"
                            value="{{ old('situacion_medicamentos', $information->situacion_medicamentos ?? '') }}">
                    </div>
                    <div class="field">
                        <label for="detalle_medicamentos">Detalle de medicamentos (opcional)</label>
                        <textarea id="detalle_medicamentos" name="detalle_medicamentos" maxlength="2000" rows="3">{{ old('detalle_medicamentos', $information->detalle_medicamentos ?? '') }}</textarea>
                    </div>
                    <div class="field">
                        <label for="antecedentes">Antecedentes (opcional)</label>
                        <textarea id="antecedentes" name="antecedentes" maxlength="4000" rows="4">{{ old('antecedentes', $information->antecedentes ?? '') }}</textarea>
                    </div>
                    <button class="button" type="submit">Guardar declaración</button>
                </form>
                <p><a href="{{ route('panel') }}">Volver al panel</a>
                    · <a href="{{ route('portal.profile') }}">Perfil</a></p>
            @endif
        </div>
    </section>
@endsection
