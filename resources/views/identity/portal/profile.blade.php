@extends('layouts.clinic')

@section('title', 'Perfil — Clínica Vida Salud')

@section('content')
    <section class="app-page" aria-labelledby="page-title">
        <p class="app-breadcrumb">Portal / Perfil</p>
        <h1 id="page-title">Perfil y preferencias</h1>
        <p class="lead">Actualice su cuenta y elija si desea recordatorios de citas. Verificar el correo no activa avisos por sí solo. Habilitarlos exige esa elección expresa y un correo verificado. Desactivarlos detiene los avisos aún no entregados. Esta pantalla no cambia el rol, los permisos ni el vínculo con un registro de paciente.</p>

        @if (session('status'))
            <p class="form-status" role="status">{{ session('status') }}</p>
        @endif
        @if ($errors->any())
            <div class="form-errors" role="alert"><p>{{ $errors->first() }}</p></div>
        @endif

        @if ($enabled && $account)
            <form class="page-form" method="post" action="{{ route('portal.profile.update', absolute: false) }}" novalidate>
                @csrf
                <div class="field">
                    <label for="nombre_usuario">Nombre de usuario</label>
                    <input id="nombre_usuario" name="nombre_usuario" type="text" required maxlength="100"
                        value="{{ old('nombre_usuario', $account->nombre_usuario) }}" autocomplete="username">
                </div>
                <div class="field">
                    <label for="correo_acceso">Correo de acceso</label>
                    <input id="correo_acceso" name="correo_acceso" type="text" maxlength="255"
                        value="{{ old('correo_acceso', $account->correo_acceso) }}" autocomplete="email">
                </div>
                <div class="field">
                    <label for="password">Nueva contraseña (opcional)</label>
                    <input id="password" name="password" type="password" minlength="12" autocomplete="new-password">
                </div>
                <div class="field">
                    <label for="password_confirmation">Confirmar contraseña</label>
                    <input id="password_confirmation" name="password_confirmation" type="password" minlength="12" autocomplete="new-password">
                </div>
                <fieldset class="field">
                    <legend>Recordatorios de citas</legend>
                    <input type="hidden" name="recordatorios_habilitados" value="0">
                    <label>
                        <input type="checkbox" name="recordatorios_habilitados" value="1"
                            @checked((string) old('recordatorios_habilitados', $preferences['recordatorios_habilitados'] ? '1' : '0') === '1')>
                        Recibir recordatorios por correo
                    </label>
                    <p>El canal es correo electrónico. Cambiar el correo anula la verificación y no puede dejar los avisos activos. No se envían avisos de citas anteriores a esta elección.</p>
                    @if ($preferences['destino_verificado'])
                        <p>Correo marcado como verificado. Esa marca no prueba la entrega ni otorga un vínculo de paciente.</p>
                    @else
                        <p>El correo aún no está verificado.</p>
                    @endif
                </fieldset>
                <button class="button" type="submit">Guardar perfil</button>
            </form>

            @if ($account->correo_acceso)
                <form class="page-form" method="post" action="{{ route('portal.profile.verify', absolute: false) }}">
                    @csrf
                    <p>Marcar el correo como verificado acredita que puede usarlo. No confirma identidad, no aprueba el vínculo con un paciente y no habilita recordatorios.</p>
                    <button class="button" type="submit">Marcar correo como verificado</button>
                </form>
            @endif
            <p><a href="{{ route('panel') }}">Volver al panel</a></p>
        @else
            <p>El portal no está habilitado.</p>
            <p><a href="{{ route('panel') }}">Volver al panel</a></p>
        @endif
    </section>
@endsection
