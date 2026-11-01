@extends('layouts.clinic')

@section('title', 'Disponibilidad — Clínica Vida Salud')

@php
    $selectedPhysician = $medico ? $physicians->firstWhere('id_medico', (int) $medico) : null;
    $selectedSpecialty = $especialidad ? $specialties->firstWhere('id_especialidad', (int) $especialidad) : null;
    try {
        $calendarDay = $fecha !== ''
            ? \Illuminate\Support\Carbon::createFromFormat('!Y-m-d', $fecha, 'UTC')
            : \Illuminate\Support\Carbon::now('UTC')->startOfDay();
        if ($calendarDay === false) {
            $calendarDay = \Illuminate\Support\Carbon::now('UTC')->startOfDay();
        }
    } catch (\Throwable) {
        $calendarDay = \Illuminate\Support\Carbon::now('UTC')->startOfDay();
    }
    $today = \Illuminate\Support\Carbon::now('UTC')->startOfDay();
    $monthStart = $calendarDay->copy()->startOfMonth();
    $monthEnd = $monthStart->copy()->endOfMonth();
    $pad = $monthStart->isoWeekday() - 1;
    $monthNames = [1 => 'Enero', 2 => 'Febrero', 3 => 'Marzo', 4 => 'Abril', 5 => 'Mayo', 6 => 'Junio', 7 => 'Julio', 8 => 'Agosto', 9 => 'Septiembre', 10 => 'Octubre', 11 => 'Noviembre', 12 => 'Diciembre'];
    $weekdayNames = ['Lu', 'Ma', 'Mi', 'Ju', 'Vi', 'Sá', 'Do'];
    $prevMonth = $monthStart->copy()->subMonth();
    $nextMonth = $monthStart->copy()->addMonth();
    $prevFecha = $prevMonth->greaterThan($today) ? $prevMonth->copy() : $today->copy();
    $canPrev = $prevMonth->copy()->endOfMonth()->gte($today);
    $queryBase = array_filter([
        'especialidad' => $especialidad,
        'medico' => $medico,
    ], static fn ($value) => $value !== null && $value !== '');
    $canBook = in_array(\App\Modules\Identity\PermissionCatalog::BOOK_APPOINTMENTS, $identityPermissions ?? [], true);
@endphp

@section('content')
    <section class="app-page" aria-labelledby="page-title">
        <p class="app-breadcrumb">Atención / Disponibilidad</p>
        <h1 id="page-title">Disponibilidad</h1>
        <p class="lead">Consulte los turnos libres por médico y fecha. Ver un hueco no lo reserva; la programación vuelve a comprobarlo.</p>

        @if ($status)
            <p class="form-errors" role="alert">{{ $status }}</p>
        @endif
        @if ($errors->any())
            <div class="form-errors" role="alert"><p>{{ $errors->first() }}</p></div>
        @endif

        <form class="filter-bar" method="get" action="{{ route('availability.consult', absolute: false) }}">
            <div class="field">
                <label for="especialidad">Especialidad</label>
                <select id="especialidad" name="especialidad">
                    <option value="">Seleccione</option>
                    @foreach ($specialties as $specialty)
                        <option value="{{ $specialty->id_especialidad }}" @selected((string) $especialidad === (string) $specialty->id_especialidad)>
                            {{ $specialty->nombre }}
                        </option>
                    @endforeach
                </select>
            </div>
            <div class="field">
                <label for="medico">Médico</label>
                <select id="medico" name="medico">
                    <option value="">Seleccione</option>
                    @foreach ($physicians as $physician)
                        <option value="{{ $physician->id_medico }}" @selected((string) $medico === (string) $physician->id_medico)>
                            {{ $physician->nombres }} {{ $physician->apellidos }}
                        </option>
                    @endforeach
                </select>
                @if ($especialidad && $physicians->isEmpty())
                    <p class="field-note">No hay médicos activos para esa especialidad.</p>
                @endif
            </div>
            <div class="field">
                <label for="fecha">Fecha</label>
                <input id="fecha" name="fecha" type="date" value="{{ $fecha }}" min="{{ $today->toDateString() }}">
            </div>
            <button class="button button-inline" type="submit">Consultar</button>
        </form>

        <div class="availability-board">
            <section class="calendar-card" aria-labelledby="calendar-title">
                <div class="calendar-nav">
                    @if ($canPrev)
                        <a class="calendar-nav-btn" href="{{ route('availability.consult', $queryBase + ['fecha' => $prevFecha->toDateString()], false) }}" aria-label="Mes anterior">‹</a>
                    @else
                        <span class="calendar-nav-btn is-disabled" aria-hidden="true">‹</span>
                    @endif
                    <h2 id="calendar-title">{{ $monthNames[(int) $monthStart->month] }} {{ $monthStart->year }}</h2>
                    <a class="calendar-nav-btn" href="{{ route('availability.consult', $queryBase + ['fecha' => $nextMonth->toDateString()], false) }}" aria-label="Mes siguiente">›</a>
                </div>
                <div class="calendar-grid">
                    @foreach ($weekdayNames as $weekdayName)
                        <span class="calendar-weekday">{{ $weekdayName }}</span>
                    @endforeach
                    @for ($i = 0; $i < $pad; $i++)
                        <span class="calendar-day is-empty"></span>
                    @endfor
                    @for ($day = 1; $day <= $monthEnd->day; $day++)
                        @php
                            $cell = $monthStart->copy()->day($day);
                            $iso = $cell->toDateString();
                            $isPast = $cell->lt($today);
                            $isSelected = $fecha === $iso;
                        @endphp
                        @if ($isPast)
                            <span class="calendar-day is-past">{{ $day }}</span>
                        @else
                            <a class="calendar-day @if ($isSelected) is-selected @endif" href="{{ route('availability.consult', $queryBase + ['fecha' => $iso], false) }}">{{ $day }}</a>
                        @endif
                    @endfor
                </div>
            </section>

            <section class="slots-card" aria-labelledby="slots-title">
                @if ($selectedPhysician)
                    <h2 id="slots-title">{{ $selectedPhysician->nombres }} {{ $selectedPhysician->apellidos }}</h2>
                    <p class="slots-meta">
                        {{ $selectedSpecialty->nombre ?? 'Especialidad' }}
                        @if ($fecha !== '')
                            · {{ $calendarDay->day }} de {{ $monthNames[(int) $calendarDay->month] }} de {{ $calendarDay->year }}
                        @endif
                    </p>
                @else
                    <h2 id="slots-title">Intervalos disponibles</h2>
                    <p class="slots-meta">Seleccione especialidad, médico y fecha, y pulse Consultar.</p>
                @endif

                <p class="slots-legend"><span class="slot-dot is-free" aria-hidden="true"></span> Libre</p>

                @if ($slots === null)
                    <p>Todavía no se ha consultado un día.</p>
                @elseif ($slots === [])
                    <p>No hay intervalos disponibles para esos criterios. Pruebe otra fecha o médico.</p>
                @else
                    <div class="table-wrap">
                        <table class="data-table">
                            <thead>
                                <tr>
                                    <th>Hora</th>
                                    <th>Estado</th>
                                    <th>Acción</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($slots as $slot)
                                    <tr>
                                        <td>{{ $slot['hora_inicio'] }} – {{ $slot['hora_fin'] }}</td>
                                        <td><span class="slot-status"><span class="slot-dot is-free" aria-hidden="true"></span> Libre</span></td>
                                        <td>
                                            @if ($canBook)
                                                <a class="button-outline" href="{{ route('appointments.create', [
                                                    'especialidad' => $especialidad,
                                                    'medico' => $medico,
                                                    'fecha' => $fecha,
                                                    'hora_inicio' => $slot['hora_inicio'],
                                                    'hora_fin' => $slot['hora_fin'],
                                                ], false) }}">Reservar</a>
                                            @else
                                                —
                                            @endif
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                    <p class="slots-footnote">Seleccionar un intervalo no lo reserva. La cita se registra solo al confirmar la programación.</p>
                @endif
            </section>
        </div>
    </section>
@endsection
