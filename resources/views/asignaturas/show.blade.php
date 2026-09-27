<x-app-layout>
    @push('styles')
        <link rel="stylesheet" media="screen, print" href="{{ asset('smartadmin/css/miscellaneous/fullcalendar/fullcalendar.bundle.css') }}">
        <style>
            #calendario-asignatura .fc-event.fc-sin-clase,
            #calendario-asignatura .fc-event.fc-sin-clase:hover,
            #calendario-asignatura .fc-event-dot.fc-sin-clase,
            #calendario-asignatura .fc-list-item.fc-sin-clase {
                background: #dc3545 !important;
                border-color: #bd2130 !important;
                color: #fff !important;
            }
        </style>
    @endpush

    <ol class="breadcrumb page-breadcrumb">
        <li class="breadcrumb-item"><a href="{{ route('dashboard') }}">{{ config('app.name') }}</a></li>
        <li class="breadcrumb-item"><a href="{{ route('asignaturas.index') }}">Asignaturas</a></li>
        <li class="breadcrumb-item active">{{ $asignatura->nombre }}</li>
    </ol>

    <div class="subheader">
        <h1 class="subheader-title">
            <i class="subheader-icon fal fa-book"></i> {{ $asignatura->nombre }}
            <small>
                {{ $asignatura->fecha_inicio->format('d/m/Y') }} – {{ $asignatura->fecha_termino->format('d/m/Y') }}
                @if ($asignatura->descripcion)
                    · {{ $asignatura->descripcion }}
                @endif
            </small>
        </h1>
        <div class="subheader-block">
            <a href="{{ route('asignaturas.edit', $asignatura) }}" class="btn btn-outline-primary">Editar</a>
        </div>
    </div>

    @if (session('status'))
        <div class="alert alert-success">{{ session('status') }}</div>
    @endif

    <div class="panel">
        <div class="panel-container show">
            <div class="panel-content">
                <ul class="nav nav-tabs" role="tablist">
                    <li class="nav-item">
                        <a class="nav-link active" data-toggle="tab" href="#calendario" role="tab">Calendario</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" data-toggle="tab" href="#lista" role="tab">Lista</a>
                    </li>
                </ul>
                <div class="tab-content pt-3">
                    <div class="tab-pane fade show active" id="calendario" role="tabpanel">
                        <div id="calendario-asignatura"></div>
                    </div>
                    <div class="tab-pane fade" id="lista" role="tabpanel">
                        <div class="table-responsive">
                            <table class="table table-bordered table-hover mb-0">
                                <thead>
                                    <tr>
                                        <th>Clase</th>
                                        <th>Fecha</th>
                                        <th>Horario</th>
                                        <th>Horas cronológicas</th>
                                        <th>Horas pedagógicas</th>
                                        <th>Comentario</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @forelse ($asignatura->clases as $clase)
                                        <tr class="{{ $clase->sin_clase ? 'text-danger' : '' }}">
                                            <td>{{ $clase->sin_clase ? 'Sin clase' : $clase->numero }}</td>
                                            <td>{{ $clase->fecha->format('d/m/Y') }}</td>
                                            <td>{{ $clase->horario() ?? '—' }}</td>
                                            <td>{{ $clase->horas_cronologicas ?? '—' }}</td>
                                            <td>{{ $clase->horas_pedagogicas ?? '—' }}</td>
                                            <td>{{ $clase->comentario ?? '—' }}</td>
                                        </tr>
                                    @empty
                                        <tr>
                                            <td colspan="6">No hay fechas en el período. Define un horario semanal para generar las clases.</td>
                                        </tr>
                                    @endforelse
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="d-flex justify-content-end mt-3">
        <a href="{{ route('asignaturas.documento', $asignatura) }}" class="btn btn-primary">Paso siguiente</a>
    </div>

    @push('scripts')
        <script src="{{ asset('smartadmin/js/miscellaneous/fullcalendar/fullcalendar.bundle.js') }}"></script>
        <script>
            if (typeof moment !== 'undefined') {
                moment.locale('es', {
                    months: 'enero_febrero_marzo_abril_mayo_junio_julio_agosto_septiembre_octubre_noviembre_diciembre'.split('_'),
                    monthsShort: 'ene_feb_mar_abr_may_jun_jul_ago_sep_oct_nov_dic'.split('_'),
                    weekdays: 'domingo_lunes_martes_miércoles_jueves_viernes_sábado'.split('_'),
                    weekdaysShort: 'dom_lun_mar_mié_jue_vie_sáb'.split('_'),
                    weekdaysMin: 'do_lu_ma_mi_ju_vi_sá'.split('_'),
                    longDateFormat: {
                        LT: 'H:mm',
                        L: 'DD/MM/YYYY',
                        LL: 'D [de] MMMM [de] YYYY',
                        LLL: 'D [de] MMMM [de] YYYY H:mm',
                        LLLL: 'dddd, D [de] MMMM [de] YYYY H:mm'
                    },
                    week: { dow: 1, doy: 4 }
                });
            }

            var calendario = document.getElementById('calendario-asignatura');
            var eventos = {{ \Illuminate\Support\Js::from($asignatura->clases->map(function ($clase) {
                $inicio = $clase->fecha->toDateString();
                $fin = $inicio;

                if ($clase->hora_inicio && $clase->hora_termino) {
                    $inicio .= 'T'.substr((string) $clase->hora_inicio, 0, 5).':00';
                    $fin = $clase->fecha->toDateString().'T'.substr((string) $clase->hora_termino, 0, 5).':00';
                }

                return [
                    'title' => $clase->sin_clase
                        ? 'Sin clase'.($clase->comentario ? ': '.$clase->comentario : '')
                        : 'Clase '.$clase->numero,
                    'start' => $inicio,
                    'end' => $clase->hora_inicio ? $fin : null,
                    'allDay' => $clase->hora_inicio === null,
                    'backgroundColor' => $clase->sin_clase ? '#dc3545' : '#886ab5',
                    'borderColor' => $clase->sin_clase ? '#bd2130' : '#7a59ad',
                    'textColor' => '#fff',
                    'className' => $clase->sin_clase ? 'fc-sin-clase' : '',
                ];
            })) }};

            var calendar = new FullCalendar.Calendar(calendario, {
                plugins: ['dayGrid', 'timeGrid', 'list', 'interaction', 'bootstrap'],
                themeSystem: 'bootstrap',
                defaultView: 'dayGridMonth',
                defaultDate: '{{ $asignatura->fecha_inicio->toDateString() }}',
                firstDay: 1,
                editable: false,
                navLinks: true,
                eventLimit: true,
                height: 650,
                header: {
                    left: 'prev,next today',
                    center: 'title',
                    right: 'dayGridMonth,timeGridWeek,listWeek'
                },
                buttonText: {
                    today: 'Hoy',
                    month: 'Mes',
                    week: 'Semana',
                    day: 'Día',
                    list: 'Lista'
                },
                events: eventos
            });

            calendar.render();

            $('a[data-toggle="tab"]').on('shown.bs.tab', function () {
                calendar.updateSize();
            });
        </script>
    @endpush
</x-app-layout>
