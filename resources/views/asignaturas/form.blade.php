<x-app-layout>
    @push('styles')
        <link rel="stylesheet" media="screen, print" href="{{ asset('smartadmin/css/formplugins/bootstrap-datepicker/bootstrap-datepicker.css') }}">
    @endpush

    @php
        $mostrarFecha = function (mixed $valor): string {
            if ($valor instanceof \Carbon\CarbonInterface) {
                return $valor->format('d/m/Y');
            }

            if (! is_string($valor) || $valor === '') {
                return '';
            }

            if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $valor) === 1) {
                return \Carbon\Carbon::createFromFormat('Y-m-d', $valor)->format('d/m/Y');
            }

            return $valor;
        };

        $filasHorario = old('horarios');

        if ($filasHorario === null) {
            $filasHorario = $asignatura->exists
                ? $asignatura->horarios->map(fn ($horario): array => [
                    'dia' => $horario->dia->value,
                    'hora_inicio' => substr((string) $horario->hora_inicio, 0, 5),
                    'hora_termino' => substr((string) $horario->hora_termino, 0, 5),
                ])->all()
                : [];
        }

        if ($filasHorario === []) {
            $filasHorario = [['dia' => '', 'hora_inicio' => '', 'hora_termino' => '']];
        }

        $fechasOrigen = old('fechas_sin_clase');

        if ($fechasOrigen === null) {
            $fechasOrigen = $asignatura->exists
                ? $asignatura->fechasExcluidas->map(fn ($excluida): array => [
                    'fecha' => $excluida->fecha->format('Y-m-d'),
                    'comentario' => $excluida->comentario,
                ])->all()
                : [];
        }

        $fechasSinClase = collect($fechasOrigen)->map(function (mixed $item) use ($mostrarFecha): array {
            if (is_string($item)) {
                return ['fecha' => $mostrarFecha($item), 'comentario' => ''];
            }

            return [
                'fecha' => $mostrarFecha($item['fecha'] ?? ''),
                'comentario' => $item['comentario'] ?? '',
            ];
        });
    @endphp

    <ol class="breadcrumb page-breadcrumb">
        <li class="breadcrumb-item"><a href="{{ route('dashboard') }}">{{ config('app.name') }}</a></li>
        <li class="breadcrumb-item"><a href="{{ route('asignaturas.index') }}">Asignaturas</a></li>
        <li class="breadcrumb-item active">{{ $asignatura->exists ? 'Editar' : 'Preparar' }}</li>
    </ol>

    <div class="subheader">
        <h1 class="subheader-title">
            <i class="subheader-icon fal fa-edit"></i> {{ $asignatura->exists ? 'Editar' : 'Preparar' }} <span class="fw-300">asignatura</span>
        </h1>
    </div>

    <form method="POST" action="{{ $asignatura->exists ? route('asignaturas.update', $asignatura) : route('asignaturas.store') }}">
        @csrf
        @if ($asignatura->exists)
            @method('PUT')
        @endif

        <div class="row">
            <div class="col-xl-12">
                <div class="panel">
                    <div class="panel-hdr">
                        <h2>Definir <span class="fw-300">asignatura</span></h2>
                    </div>
                    <div class="panel-container show">
                        <div class="panel-content">
                            <div class="form-group">
                                <label class="form-label" for="nombre">Nombre</label>
                                <input type="text" id="nombre" name="nombre" class="form-control @error('nombre') is-invalid @enderror" value="{{ old('nombre', $asignatura->nombre) }}" required>
                                @error('nombre')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>
                            <div class="form-group mb-0">
                                <label class="form-label" for="descripcion">Descripción</label>
                                <textarea id="descripcion" name="descripcion" rows="3" class="form-control @error('descripcion') is-invalid @enderror">{{ old('descripcion', $asignatura->descripcion) }}</textarea>
                                @error('descripcion')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>
                        </div>
                    </div>
                </div>

                <div class="panel">
                    <div class="panel-hdr">
                        <h2>Definir <span class="fw-300">fechas</span></h2>
                    </div>
                    <div class="panel-container show">
                        <div class="panel-content">
                            <div class="form-row">
                                <div class="form-group col-md-6">
                                    <label class="form-label" for="fecha_inicio">Fecha de inicio</label>
                                    <input type="text" id="fecha_inicio" name="fecha_inicio" class="form-control js-datepicker @error('fecha_inicio') is-invalid @enderror" value="{{ $mostrarFecha(old('fecha_inicio', $asignatura->fecha_inicio)) }}" placeholder="dd/mm/aaaa" autocomplete="off" required>
                                    @error('fecha_inicio')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>
                                <div class="form-group col-md-6">
                                    <label class="form-label" for="fecha_termino">Fecha de término</label>
                                    <input type="text" id="fecha_termino" name="fecha_termino" class="form-control js-datepicker @error('fecha_termino') is-invalid @enderror" value="{{ $mostrarFecha(old('fecha_termino', $asignatura->fecha_termino)) }}" placeholder="dd/mm/aaaa" autocomplete="off" required>
                                    @error('fecha_termino')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="panel">
                    <div class="panel-hdr">
                        <h2>Definir <span class="fw-300">horario semanal</span></h2>
                    </div>
                    <div class="panel-container show">
                        <div class="panel-content">
                            <div id="horarios">
                                @foreach ($filasHorario as $indice => $fila)
                                    <div class="form-row align-items-end horario-fila mb-2">
                                        <div class="form-group col-md-4">
                                            <label class="form-label">Día</label>
                                            <select name="horarios[{{ $indice }}][dia]" class="form-control @error('horarios.'.$indice.'.dia') is-invalid @enderror">
                                                <option value="">Selecciona</option>
                                                @foreach (\App\DiaSemana::cases() as $dia)
                                                    <option value="{{ $dia->value }}" @selected((string) ($fila['dia'] ?? '') === (string) $dia->value)>{{ $dia->etiqueta() }}</option>
                                                @endforeach
                                            </select>
                                            @error('horarios.'.$indice.'.dia')
                                                <div class="invalid-feedback d-block">{{ $message }}</div>
                                            @enderror
                                        </div>
                                        <div class="form-group col-md-3">
                                            <label class="form-label">Hora de inicio</label>
                                            <input type="time" name="horarios[{{ $indice }}][hora_inicio]" class="form-control @error('horarios.'.$indice.'.hora_inicio') is-invalid @enderror" value="{{ $fila['hora_inicio'] ?? '' }}">
                                            @error('horarios.'.$indice.'.hora_inicio')
                                                <div class="invalid-feedback">{{ $message }}</div>
                                            @enderror
                                        </div>
                                        <div class="form-group col-md-3">
                                            <label class="form-label">Hora de término</label>
                                            <input type="time" name="horarios[{{ $indice }}][hora_termino]" class="form-control @error('horarios.'.$indice.'.hora_termino') is-invalid @enderror" value="{{ $fila['hora_termino'] ?? '' }}">
                                            @error('horarios.'.$indice.'.hora_termino')
                                                <div class="invalid-feedback">{{ $message }}</div>
                                            @enderror
                                        </div>
                                        <div class="form-group col-md-2">
                                            <button type="button" class="btn btn-outline-danger btn-block quitar-horario">Quitar</button>
                                        </div>
                                    </div>
                                @endforeach
                            </div>
                            <button type="button" id="agregar-horario" class="btn btn-outline-primary">Agregar horario</button>
                        </div>
                    </div>
                </div>

                <div class="panel">
                    <div class="panel-hdr">
                        <h2>Fechas <span class="fw-300">sin clase</span></h2>
                    </div>
                    <div class="panel-container show">
                        <div class="panel-content">
                            <div class="form-row align-items-end">
                                <div class="form-group col-md-4">
                                    <label class="form-label" for="fecha-sin-clase">Agregar fecha</label>
                                    <input type="text" id="fecha-sin-clase" class="form-control js-datepicker" placeholder="dd/mm/aaaa" autocomplete="off">
                                </div>
                                <div class="form-group col-md-3">
                                    <button type="button" id="agregar-fecha" class="btn btn-outline-primary">Agregar</button>
                                </div>
                            </div>
                            @php
                                $erroresFechas = collect($errors->getMessages())->filter(
                                    fn (array $mensajes, string $campo): bool => str_starts_with($campo, 'fechas_sin_clase')
                                );
                            @endphp
                            @if ($erroresFechas->isNotEmpty())
                                <div class="text-danger mb-2">
                                    @foreach ($erroresFechas as $mensajes)
                                        @foreach ($mensajes as $mensaje)
                                            <div>{{ $mensaje }}</div>
                                        @endforeach
                                    @endforeach
                                </div>
                            @endif
                            <ul id="fechas-sin-clase" class="list-group">
                                @foreach ($fechasSinClase as $indice => $fecha)
                                    <li class="list-group-item">
                                        <div class="d-flex justify-content-between align-items-center mb-2">
                                            <span class="fw-500">{{ $fecha['fecha'] }}</span>
                                            <button type="button" class="btn btn-sm btn-outline-danger quitar-fecha">Quitar</button>
                                        </div>
                                        <input type="hidden" name="fechas_sin_clase[{{ $indice }}][fecha]" value="{{ $fecha['fecha'] }}">
                                        <input type="text" name="fechas_sin_clase[{{ $indice }}][comentario]" class="form-control" value="{{ $fecha['comentario'] }}" placeholder="Comentario opcional">
                                    </li>
                                @endforeach
                            </ul>
                        </div>
                    </div>
                </div>

                <button type="submit" class="btn btn-primary">{{ $asignatura->exists ? 'Guardar cambios' : 'Preparar asignatura' }}</button>
                <a href="{{ route('asignaturas.index') }}" class="btn btn-outline-secondary">Volver al listado</a>
            </div>
        </div>
    </form>

    <template id="plantilla-horario">
        <div class="form-row align-items-end horario-fila mb-2">
            <div class="form-group col-md-4">
                <label class="form-label">Día</label>
                <select data-nombre="dia" class="form-control">
                    <option value="">Selecciona</option>
                    @foreach (\App\DiaSemana::cases() as $dia)
                        <option value="{{ $dia->value }}">{{ $dia->etiqueta() }}</option>
                    @endforeach
                </select>
            </div>
            <div class="form-group col-md-3">
                <label class="form-label">Hora de inicio</label>
                <input type="time" data-nombre="hora_inicio" class="form-control">
            </div>
            <div class="form-group col-md-3">
                <label class="form-label">Hora de término</label>
                <input type="time" data-nombre="hora_termino" class="form-control">
            </div>
            <div class="form-group col-md-2">
                <button type="button" class="btn btn-outline-danger btn-block quitar-horario">Quitar</button>
            </div>
        </div>
    </template>

    @push('scripts')
        <script src="{{ asset('smartadmin/js/formplugins/bootstrap-datepicker/bootstrap-datepicker.js') }}"></script>
        <script>
            $.fn.datepicker.dates.es = {
                days: ['Domingo', 'Lunes', 'Martes', 'Miércoles', 'Jueves', 'Viernes', 'Sábado'],
                daysShort: ['Dom', 'Lun', 'Mar', 'Mié', 'Jue', 'Vie', 'Sáb'],
                daysMin: ['Do', 'Lu', 'Ma', 'Mi', 'Ju', 'Vi', 'Sá'],
                months: ['Enero', 'Febrero', 'Marzo', 'Abril', 'Mayo', 'Junio', 'Julio', 'Agosto', 'Septiembre', 'Octubre', 'Noviembre', 'Diciembre'],
                monthsShort: ['Ene', 'Feb', 'Mar', 'Abr', 'May', 'Jun', 'Jul', 'Ago', 'Sep', 'Oct', 'Nov', 'Dic'],
                today: 'Hoy',
                clear: 'Limpiar',
                format: 'dd/mm/yyyy',
                weekStart: 1
            };

            $('.js-datepicker').datepicker({
                format: 'dd/mm/yyyy',
                language: 'es',
                weekStart: 1,
                autoclose: true,
                todayHighlight: true,
                orientation: 'bottom left',
                templates: {
                    leftArrow: '<i class="fal fa-angle-left" style="font-size: 1.25rem"></i>',
                    rightArrow: '<i class="fal fa-angle-right" style="font-size: 1.25rem"></i>'
                }
            });

            var horarios = document.getElementById('horarios');
            var plantilla = document.getElementById('plantilla-horario');

            document.getElementById('agregar-horario').addEventListener('click', function () {
                var indice = horarios.querySelectorAll('.horario-fila').length;
                var nodo = plantilla.content.firstElementChild.cloneNode(true);
                nodo.querySelectorAll('[data-nombre]').forEach(function (campo) {
                    campo.name = 'horarios[' + indice + '][' + campo.dataset.nombre + ']';
                });
                horarios.appendChild(nodo);
            });

            horarios.addEventListener('click', function (evento) {
                if (! evento.target.classList.contains('quitar-horario')) {
                    return;
                }

                var filas = horarios.querySelectorAll('.horario-fila');
                if (filas.length === 1) {
                    filas[0].querySelectorAll('input, select').forEach(function (campo) {
                        campo.value = '';
                    });
                    return;
                }

                evento.target.closest('.horario-fila').remove();
            });

            document.getElementById('agregar-fecha').addEventListener('click', function () {
                var entrada = document.getElementById('fecha-sin-clase');
                var lista = document.getElementById('fechas-sin-clase');
                var valor = entrada.value.trim();
                if (! valor) {
                    return;
                }

                var existentes = Array.from(lista.querySelectorAll('input[type="hidden"]')).map(function (campo) {
                    return campo.value;
                });

                if (existentes.indexOf(valor) !== -1) {
                    return;
                }

                var indice = lista.children.length;
                var item = document.createElement('li');
                item.className = 'list-group-item';
                item.innerHTML = '<div class="d-flex justify-content-between align-items-center mb-2"><span class="fw-500"></span><button type="button" class="btn btn-sm btn-outline-danger quitar-fecha">Quitar</button></div><input type="hidden"><input type="text" class="form-control" placeholder="Comentario opcional">';
                item.querySelector('span').textContent = valor;
                item.querySelector('input[type="hidden"]').name = 'fechas_sin_clase[' + indice + '][fecha]';
                item.querySelector('input[type="hidden"]').value = valor;
                item.querySelector('input[type="text"]').name = 'fechas_sin_clase[' + indice + '][comentario]';
                lista.appendChild(item);
                entrada.value = '';
            });

            document.getElementById('fechas-sin-clase').addEventListener('click', function (evento) {
                if (evento.target.classList.contains('quitar-fecha')) {
                    evento.target.closest('li').remove();
                }
            });
        </script>
    @endpush
</x-app-layout>
