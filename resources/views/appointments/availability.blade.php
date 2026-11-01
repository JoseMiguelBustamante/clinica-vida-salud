@extends('layouts.clinic')

@section('title', 'Disponibilidad — Clínica Vida Salud')

@php
    $weekdayNames = [1 => 'Lunes', 2 => 'Martes', 3 => 'Miércoles', 4 => 'Jueves', 5 => 'Viernes'];
    $shortWeekdays = ['Lu', 'Ma', 'Mi', 'Ju', 'Vi', 'Sá', 'Do'];
    $monthNames = [1 => 'Enero', 2 => 'Febrero', 3 => 'Marzo', 4 => 'Abril', 5 => 'Mayo', 6 => 'Junio', 7 => 'Julio', 8 => 'Agosto', 9 => 'Septiembre', 10 => 'Octubre', 11 => 'Noviembre', 12 => 'Diciembre'];
    $queryBase = array_filter([
        'especialidad' => $especialidad,
        'medico' => $medico,
        'vista' => $vista,
    ], static fn ($value) => $value !== null && $value !== '');
    $canBook = in_array(\App\Modules\Identity\PermissionCatalog::BOOK_APPOINTMENTS, $identityPermissions ?? [], true);
    $trackHeight = ($boardEndHour - $boardStartHour) * $hourHeight;
    $eventsByDay = collect($events)->groupBy('fecha');
    $legend = collect($events)->unique('id_medico')->values();
    $monthStart = $today->copy()->startOfMonth();
    if ($vista === 'mes') {
        try {
            $parsedMonth = \Illuminate\Support\Carbon::createFromFormat('!Y-m-d', $fecha, 'UTC');
            if ($parsedMonth !== false) {
                $monthStart = $parsedMonth->copy()->startOfMonth();
            }
        } catch (\Throwable) {
        }
    }
    $monthEnd = $monthStart->copy()->endOfMonth();
    $pad = $monthStart->isoWeekday() - 1;
@endphp

@section('content')
    <section class="app-page" aria-labelledby="page-title">
        <p class="app-breadcrumb">Atención / Disponibilidad</p>
        <div class="page-toolbar">
            <div>
                <h1 id="page-title">Disponibilidad</h1>
                <p class="lead">Consulta los intervalos libres por médico y especialidad. Ver un hueco no lo reserva.</p>
            </div>
            @if ($canBook)
                <a class="button button-inline" href="{{ route('appointments.create') }}">Nueva cita</a>
            @endif
        </div>

        @if ($status)
            <p class="form-errors" role="alert">{{ $status }}</p>
        @endif
        @if ($errors->any())
            <div class="form-errors" role="alert"><p>{{ $errors->first() }}</p></div>
        @endif

        <form class="filter-bar availability-filters" method="get" action="{{ route('availability.consult', absolute: false) }}">
            <input type="hidden" name="vista" value="{{ $vista }}">
            <input type="hidden" name="fecha" value="{{ $fecha }}">
            <div class="field">
                <label for="medico">Médico</label>
                <select id="medico" name="medico">
                    <option value="">Todos los médicos</option>
                    @foreach ($physicians as $physician)
                        <option value="{{ $physician->id_medico }}" @selected((string) $medico === (string) $physician->id_medico)>
                            {{ $physician->nombres }} {{ $physician->apellidos }}
                        </option>
                    @endforeach
                </select>
            </div>
            <div class="field">
                <label for="especialidad">Especialidad</label>
                <select id="especialidad" name="especialidad">
                    <option value="">Todas</option>
                    @foreach ($specialties as $specialty)
                        <option value="{{ $specialty->id_especialidad }}" @selected((string) $especialidad === (string) $specialty->id_especialidad)>
                            {{ $specialty->nombre }}
                        </option>
                    @endforeach
                </select>
            </div>
            <button class="button button-inline" type="submit">Consultar</button>
        </form>

        <div class="week-board">
            <div class="week-toolbar">
                <div class="week-nav">
                    <a class="week-chip" href="{{ route('availability.consult', $queryBase + ['fecha' => $today->toDateString()], false) }}">Hoy</a>
                    @if ($canPrev)
                        <a class="calendar-nav-btn" href="{{ route('availability.consult', $queryBase + ['fecha' => $prevFecha], false) }}" aria-label="Periodo anterior">‹</a>
                    @else
                        <span class="calendar-nav-btn is-disabled" aria-hidden="true">‹</span>
                    @endif
                    <a class="calendar-nav-btn" href="{{ route('availability.consult', $queryBase + ['fecha' => $nextFecha], false) }}" aria-label="Periodo siguiente">›</a>
                    <p class="week-range">{{ $rangeLabel }}</p>
                </div>
                <nav class="week-switch" aria-label="Vista del calendario">
                    <a class="@if ($vista === 'dia') is-current @endif" href="{{ route('availability.consult', array_filter(['especialidad' => $especialidad, 'medico' => $medico, 'fecha' => $fecha, 'vista' => 'dia']), false) }}">Día</a>
                    <a class="@if ($vista === 'semana') is-current @endif" href="{{ route('availability.consult', array_filter(['especialidad' => $especialidad, 'medico' => $medico, 'fecha' => $fecha, 'vista' => 'semana']), false) }}">Semana</a>
                    <a class="@if ($vista === 'mes') is-current @endif" href="{{ route('availability.consult', array_filter(['especialidad' => $especialidad, 'medico' => $medico, 'fecha' => $fecha, 'vista' => 'mes']), false) }}">Mes</a>
                </nav>
            </div>

            @if ($vista === 'mes')
                <section class="calendar-card week-month" aria-labelledby="calendar-title">
                    <h2 id="calendar-title" class="visually-hidden">{{ $rangeLabel }}</h2>
                    <div class="calendar-grid">
                        @foreach ($shortWeekdays as $weekdayName)
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
                                <a class="calendar-day @if ($isSelected) is-selected @endif" href="{{ route('availability.consult', array_filter(['especialidad' => $especialidad, 'medico' => $medico, 'vista' => 'dia', 'fecha' => $iso]), false) }}">{{ $day }}</a>
                            @endif
                        @endfor
                    </div>
                    <p class="slots-footnote">Elija un día para ver los intervalos libres en la vista diaria.</p>
                </section>
            @else
                <div class="week-scroll">
                    <div class="week-grid" style="--week-hour: {{ $hourHeight }}rem; --week-days: {{ max(1, count($days)) }}; --week-track: {{ $trackHeight }}rem; --week-hour-count: {{ count($hours) }};">
                        <div class="week-hours" aria-hidden="true">
                            <span class="week-corner"></span>
                            @foreach ($hours as $hour)
                                <span class="week-hour">{{ $hour }}</span>
                            @endforeach
                        </div>
                        @foreach ($days as $day)
                            @php
                                $iso = $day->toDateString();
                                $isPast = $day->lt($today);
                                $isToday = $day->isSameDay($today);
                                $dayEvents = $eventsByDay->get($iso, collect());
                            @endphp
                            <section class="week-day @if ($isToday) is-today @endif @if ($isPast) is-past @endif" aria-label="{{ $weekdayNames[$day->isoWeekday()] ?? '' }} {{ $day->day }}">
                                <header class="week-day-head">
                                    <span>{{ $weekdayNames[$day->isoWeekday()] ?? $day->isoFormat('dddd') }}</span>
                                    <strong>{{ $day->day }} de {{ $monthNames[(int) $day->month] }}</strong>
                                </header>
                                <div class="week-track">
                                    @foreach ($hours as $hour)
                                        <div class="week-line"></div>
                                    @endforeach
                                    @foreach ($dayEvents as $event)
                                        @php
                                            $href = $canBook ? route('appointments.create', [
                                                'especialidad' => $especialidad,
                                                'medico' => $event['id_medico'],
                                                'fecha' => $event['fecha'],
                                                'hora_inicio' => $event['hora_inicio'],
                                                'hora_fin' => $event['hora_fin'],
                                            ], false) : null;
                                        @endphp
                                        @if ($href)
                                            <a class="week-event tone-{{ $event['tone'] }}" href="{{ $href }}" style="top: {{ $event['top'] }}rem; height: {{ $event['height'] }}rem;">
                                                <span class="week-event-time">{{ $event['hora_inicio'] }} – {{ $event['hora_fin'] }}</span>
                                                <strong>Libre</strong>
                                                <span>{{ $event['medico'] }}</span>
                                            </a>
                                        @else
                                            <div class="week-event tone-{{ $event['tone'] }}" style="top: {{ $event['top'] }}rem; height: {{ $event['height'] }}rem;">
                                                <span class="week-event-time">{{ $event['hora_inicio'] }} – {{ $event['hora_fin'] }}</span>
                                                <strong>Libre</strong>
                                                <span>{{ $event['medico'] }}</span>
                                            </div>
                                        @endif
                                    @endforeach
                                </div>
                            </section>
                        @endforeach
                    </div>
                </div>
                @if (! $especialidad)
                    <p class="slots-footnote">Seleccione una especialidad para ver los intervalos libres. Ver un hueco no lo reserva.</p>
                @elseif ($physicians->isEmpty())
                    <p class="slots-footnote">No hay médicos activos para esa especialidad.</p>
                @elseif ($events === [])
                    <p class="slots-footnote">No hay intervalos disponibles para esos criterios. Pruebe otra semana o médico.</p>
                @endif
            @endif

            <footer class="week-legend">
                <p>
                    @forelse ($legend as $item)
                        <span class="week-legend-item"><span class="week-legend-dot tone-{{ $item['tone'] }}" aria-hidden="true"></span> {{ $item['medico'] }}</span>
                    @empty
                        <span class="week-legend-item"><span class="week-legend-dot tone-0" aria-hidden="true"></span> Intervalos libres</span>
                    @endforelse
                </p>
                <p>Seleccionar un intervalo no lo reserva.</p>
            </footer>
        </div>
    </section>
@endsection
