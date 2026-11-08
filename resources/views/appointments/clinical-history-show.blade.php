@extends('layouts.clinic')

@section('title', 'Detalle del historial — Clínica Vida Salud')

@section('content')
    <section class="app-page" aria-labelledby="page-title">
        <p class="app-breadcrumb">Portal / Historial médico</p>
        <h1 id="page-title">Detalle autorizado</h1>
        <p class="lead">Solo se muestran los datos de la cita cuya visualización está autorizada. No incluye diagnósticos ni notas elaboradas por profesionales de salud.</p>

        @if (! $enabled)
            <p>El portal no está habilitado.</p>
            <p><a href="{{ route('appointments.clinical-history') }}">Volver al historial</a></p>
        @elseif ($queryFailed)
            <p>No fue posible completar la consulta.</p>
            <p><a href="{{ route('appointments.clinical-history') }}">Volver al historial</a></p>
        @elseif (! $linked)
            <p>El acceso requiere un vínculo aprobado con el registro de paciente.</p>
            <p><a href="{{ route('panel') }}">Volver al panel</a></p>
        @elseif ($unavailable)
            <p>El registro no está disponible para su consulta.</p>
            <p><a href="{{ route('appointments.clinical-history') }}">Volver al historial</a></p>
        @else
            <article class="list-card detail-card">
                <dl class="detail-list">
                    <div>
                        <dt>Paciente</dt>
                        <dd>{{ $patient->apellidos }}, {{ $patient->nombres }}</dd>
                    </div>
                    <div>
                        <dt>Código</dt>
                        <dd>{{ $record->codigo }}</dd>
                    </div>
                    <div>
                        <dt>Estado</dt>
                        <dd>{{ $record->estado }} · {{ $record->modalidad }}</dd>
                    </div>
                    <div>
                        <dt>Fecha</dt>
                        <dd>{{ \Illuminate\Support\Carbon::parse((string) $record->inicio)->timezone('UTC')->format('Y-m-d') }}
                            · {{ \Illuminate\Support\Carbon::parse((string) $record->inicio)->timezone('UTC')->format('H:i') }}
                            – {{ \Illuminate\Support\Carbon::parse((string) $record->fin)->timezone('UTC')->format('H:i') }} UTC</dd>
                    </div>
                    <div>
                        <dt>Médico</dt>
                        <dd>{{ $record->medico_apellidos }}, {{ $record->medico_nombres }}
                            @if ($record->registro_profesional)
                                · {{ $record->registro_profesional }}
                            @endif
                        </dd>
                    </div>
                    <div>
                        <dt>Especialidad</dt>
                        <dd>{{ $record->especialidad_nombre }}</dd>
                    </div>
                    <div>
                        <dt>Motivo declarado</dt>
                        <dd>{{ $record->motivo_consulta ?: '—' }}</dd>
                    </div>
                    @if ($record->estado === 'Cancelada')
                        <div>
                            <dt>Motivo de cancelación</dt>
                            <dd>{{ $record->motivo_cancelacion ?: '—' }}</dd>
                        </div>
                    @endif
                </dl>
            </article>

            @if ($reminder)
                <article class="list-card detail-card" aria-labelledby="reminder-title">
                    <h2 id="reminder-title">Recordatorio por correo</h2>
                    <dl class="detail-list">
                        <div>
                            <dt>Estado</dt>
                            <dd>{{ $reminder['status_label'] }}</dd>
                        </div>
                        <div>
                            <dt>Detalle</dt>
                            <dd>{{ $reminder['detail'] }}</dd>
                        </div>
                        @if ($reminder['reminder']?->fecha_programada)
                            <div>
                                <dt>Envío previsto</dt>
                                <dd>{{ \Illuminate\Support\Carbon::parse((string) $reminder['reminder']->fecha_programada)->timezone('UTC')->format('Y-m-d H:i') }} UTC</dd>
                            </div>
                        @endif
                    </dl>
                    @unless ($reminder['preferences_ready'])
                        <p class="list-meta">Para recibir el aviso: en <a href="{{ route('portal.profile') }}">Perfil</a> active los recordatorios y marque el correo como verificado. Luego pida en recepción que reprogramen o envíen el aviso de esta cita.</p>
                    @else
                        <p class="list-meta">El envío lo hace el sistema (o recepción con «Enviar recordatorio ahora» en la ficha de la cita). Revise también la carpeta de spam.</p>
                    @endunless
                </article>
            @endif

            <p><a href="{{ route('appointments.clinical-history') }}">Volver al historial</a></p>
        @endif
    </section>
@endsection
