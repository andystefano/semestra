<x-app-layout>
    <ol class="breadcrumb page-breadcrumb">
        <li class="breadcrumb-item"><a href="{{ route('dashboard') }}">{{ config('app.name') }}</a></li>
        <li class="breadcrumb-item"><a href="{{ route('asignaturas.actividades', $asignatura) }}">Actividades</a></li>
        <li class="breadcrumb-item active">Planificación</li>
    </ol>

    <div class="subheader">
        <h1 class="subheader-title">
            <i class="subheader-icon fal fa-calendar-alt"></i> {{ $asignatura->nombre }}
            <small>Planificación</small>
        </h1>
    </div>

    @php
        $plan = $asignatura->planificacion ?? [];
        $resumen = $plan['resumen'] ?? [];
    @endphp

    @if ($plan === [])
        <div class="alert alert-warning">Todavía no hay una planificación generada.</div>
    @else
        <div class="row mb-3">
            <div class="col-md-3">
                <div class="p-3 border rounded">
                    <div class="text-muted">Minutos sugeridos</div>
                    <strong>{{ $resumen['horas_sugeridas_minutos'] ?? '—' }}</strong>
                </div>
            </div>
            <div class="col-md-3">
                <div class="p-3 border rounded">
                    <div class="text-muted">Minutos para clases</div>
                    <strong>{{ $resumen['minutos_disponibles_para_clases'] ?? '—' }}</strong>
                </div>
            </div>
            <div class="col-md-3">
                <div class="p-3 border rounded">
                    <div class="text-muted">Factor proporcional</div>
                    <strong>{{ $resumen['factor_proporcional'] ?? '—' }}</strong>
                </div>
            </div>
        </div>

        @foreach ($plan['advertencias'] ?? [] as $advertencia)
            <div class="alert alert-warning">{{ $advertencia }}</div>
        @endforeach

        <style>
            #acordeon-planificacion .card-header .btn-link {
                display: flex;
                align-items: center;
                justify-content: space-between;
                width: 100%;
                text-align: left;
                text-decoration: none;
                box-shadow: none;
            }
            #acordeon-planificacion .tag-plan {
                background: #fff !important;
                color: #000 !important;
                font-weight: 600;
            }
            #acordeon-planificacion .encabezado-sin-clase,
            #acordeon-planificacion .encabezado-sin-clase .btn-link,
            #acordeon-planificacion .encabezado-sin-clase .btn-link:hover,
            #acordeon-planificacion .encabezado-sin-clase .btn-link:focus {
                background: #dc3545;
                color: #000;
            }
            #acordeon-planificacion .encabezado-clases,
            #acordeon-planificacion .encabezado-clases .btn-link,
            #acordeon-planificacion .encabezado-clases .btn-link:hover,
            #acordeon-planificacion .encabezado-clases .btn-link:focus {
                background: #0d6efd;
                color: #fff;
            }
            #acordeon-planificacion .encabezado-evaluacion,
            #acordeon-planificacion .encabezado-evaluacion .btn-link,
            #acordeon-planificacion .encabezado-evaluacion .btn-link:hover,
            #acordeon-planificacion .encabezado-evaluacion .btn-link:focus {
                background: #ffc107;
                color: #000;
            }
            #acordeon-planificacion .encabezado-mixta,
            #acordeon-planificacion .encabezado-mixta .btn-link,
            #acordeon-planificacion .encabezado-mixta .btn-link:hover,
            #acordeon-planificacion .encabezado-mixta .btn-link:focus {
                background: linear-gradient(to right, #ffc107, #0d6efd);
                color: #000;
            }
        </style>
        <div class="accordion" id="acordeon-planificacion">
            @foreach ($dias as $indice => $dia)
                @php
                    $tipos = collect($dia['bloques'])->pluck('tipo');
                    $tieneClase = $tipos->contains('CLASE');
                    $tieneEvaluacion = $tipos->contains(fn (string $tipo): bool => $tipo !== 'CLASE');
                    $variante = match (true) {
                        $tieneClase && $tieneEvaluacion => 'encabezado-mixta',
                        $tieneClase => 'encabezado-clases',
                        $tieneEvaluacion => 'encabezado-evaluacion',
                        default => 'encabezado-sin-clase',
                    };
                    $etiquetas = $tipos->map(fn (string $tipo): string => match ($tipo) {
                        'CLASE' => 'Clase',
                        'EVALUACION' => 'Evaluación',
                        'EVALUACION_RECUPERATIVA' => 'Recuperativa',
                        'EXAMEN_1' => 'Examen 1',
                        'EXAMEN_REPETICION' => 'Repetición',
                        default => $tipo,
                    })->unique()->values();
                    if ($etiquetas->isEmpty()) {
                        $etiquetas = collect(['Sin clase']);
                    }
                @endphp
                <div class="card {{ $variante === 'encabezado-sin-clase' ? 'border-danger' : '' }}">
                    <div class="card-header {{ $variante }}" id="encabezado-dia-{{ $indice }}">
                        <button class="btn btn-link {{ $indice === 0 ? '' : 'collapsed' }}" type="button" data-toggle="collapse" data-target="#dia-{{ $indice }}" aria-expanded="{{ $indice === 0 ? 'true' : 'false' }}">
                            <span>{{ \Illuminate\Support\Carbon::parse($dia['fecha'])->format('d/m/Y') }}</span>
                            <span>
                                @foreach ($etiquetas as $etiqueta)
                                    <span class="badge tag-plan ml-1">{{ $etiqueta }}</span>
                                @endforeach
                            </span>
                        </button>
                    </div>
                    <div id="dia-{{ $indice }}" class="collapse {{ $indice === 0 ? 'show' : '' }}" data-parent="#acordeon-planificacion">
                        <div class="card-body {{ $dia['sin_clase'] ? 'text-danger' : '' }}">
                            @if ($dia['sin_clase'])
                                <div class="border border-danger rounded p-3 mb-2 text-danger bg-danger-50">
                                    <strong>Sin clase</strong>
                                    @if ($dia['comentario'])
                                        <div>{{ $dia['comentario'] }}</div>
                                    @endif
                                </div>
                            @endif
                            @foreach ($dia['bloques'] as $bloque)
                                @php
                                    $esClase = ($bloque['tipo'] ?? '') === 'CLASE';
                                    $color = $esClase ? 'border-info bg-info-50' : 'border-warning bg-warning-50';
                                @endphp
                                <div class="border rounded p-3 mb-2 {{ $color }}">
                                    <strong>{{ $bloque['tipo'] }}</strong>
                                    <span class="text-muted">· {{ $bloque['duracion_minutos'] }} minutos</span>
                                    @if (! empty($bloque['unidad']))
                                        <div>Unidad {{ $bloque['unidad'] }}</div>
                                    @endif
                                    @if (! empty($bloque['descripcion']))
                                        <div>{{ $bloque['descripcion'] }}</div>
                                    @endif
                                    @if ($esClase && ! empty($bloque['unidad']))
                                        <p class="mt-2 mb-1"><strong>Aprendizaje esperado</strong><br>{{ $bloque['aprendizaje_esperado'] ?: '—' }}</p>
                                        <p class="mb-1"><strong>Criterios de evaluación</strong></p>
                                        <ul>
                                            @forelse ($bloque['criterios_evaluacion'] ?? [] as $criterio)
                                                <li>{{ $criterio }}</li>
                                            @empty
                                                <li>—</li>
                                            @endforelse
                                        </ul>
                                        <p class="mb-1"><strong>Contenidos obligatorios</strong></p>
                                        <ul>
                                            @foreach ($bloque['contenidos_obligatorios'] ?? [] as $contenido)
                                                <li>{{ $contenido['nombre'] }} @if (($contenido['minutos_asignados'] ?? 0) > 0)({{ $contenido['minutos_asignados'] }} min)@endif</li>
                                            @endforeach
                                        </ul>
                                        <p class="mb-1"><strong>Tipo de habilidad asociada al AE</strong><br>{{ $bloque['tipo_habilidad'] ?: '—' }}</p>
                                        <p class="mb-0"><strong>Competencias personales, sociales y valóricas</strong><br>{{ $bloque['competencias_personales_sociales_valoricas'] ?: '—' }}</p>
                                        @if (($bloque['contenidos_obligatorios'] ?? []) !== [])
                                            @php
                                                $claveGuia = $dia['fecha'].'|'.($bloque['orden'] ?? 0);
                                                $guia = ($guias ?? collect())->get($claveGuia);
                                            @endphp
                                            <div class="mt-3 guia-acciones">
                                                <button type="button" class="btn btn-sm btn-primary generar-guia" data-fecha="{{ $dia['fecha'] }}" data-orden="{{ $bloque['orden'] ?? 1 }}">Generar guía de estudios</button>
                                                <a class="btn btn-sm btn-outline-primary ml-1 descargar-guia {{ $guia ? '' : 'd-none' }}" href="{{ $guia ? route('asignaturas.guias.descargar', [$asignatura, $guia]) : '#' }}">Descargar guía</a>
                                            </div>
                                        @endif
                                    @endif
                                </div>
                            @endforeach
                            @if (! $dia['sin_clase'] && $dia['bloques'] === [])
                                <div class="text-muted">Sin bloques en esta fecha.</div>
                            @endif
                        </div>
                    </div>
                </div>
            @endforeach
        </div>
    @endif

    <div id="error-planificacion" class="alert alert-danger d-none mt-3"></div>
    <div id="respuesta-planificacion" class="d-none mt-3">
        <label class="form-label" for="texto-respuesta-planificacion">Respuesta recibida. Puedes leerla y copiarla.</label>
        <textarea id="texto-respuesta-planificacion" class="form-control" rows="16" readonly></textarea>
    </div>

    <div class="d-flex justify-content-end mt-3">
        <button type="button" id="generar-planificacion" class="btn btn-primary">Generar una planificación diferente</button>
    </div>

    <div id="cargando-guia" class="d-none align-items-center justify-content-center" style="position: fixed; inset: 0; background: rgba(0, 0, 0, .45); z-index: 2000;">
        <div class="text-center text-white">
            <div class="spinner-border" role="status"></div>
            <div id="mensaje-guia" class="mt-3">redactando la guía de estudios</div>
        </div>
    </div>
    <div id="cargando-planificacion" class="d-none align-items-center justify-content-center" style="position: fixed; inset: 0; background: rgba(0, 0, 0, .45); z-index: 2000;">
        <div class="text-center text-white">
            <div class="spinner-border" role="status"></div>
            <div id="mensaje-planificacion" class="mt-3">analizando actividades</div>
        </div>
    </div>

    @push('scripts')
        <script>
            document.getElementById('generar-planificacion').addEventListener('click', function () {
                var boton = this;
                var cargando = document.getElementById('cargando-planificacion');
                var mensaje = document.getElementById('mensaje-planificacion');
                var error = document.getElementById('error-planificacion');
                var caja = document.getElementById('respuesta-planificacion');
                var texto = document.getElementById('texto-respuesta-planificacion');
                var avisos = [
                    'analizando actividades',
                    'analizando restricciones',
                    'distribuyendo actividades académicas',
                    'distribuyendo contenidos en fechas disponibles'
                ];
                var indice = 0;
                var reloj = setInterval(function () {
                    indice = (indice + 1) % avisos.length;
                    mensaje.textContent = avisos[indice];
                }, 2500);

                boton.disabled = true;
                error.classList.add('d-none');
                caja.classList.add('d-none');
                texto.value = '';
                mensaje.textContent = avisos[0];
                cargando.classList.remove('d-none');
                cargando.classList.add('d-flex');

                fetch(@json(route('asignaturas.actividades.generar', $asignatura)), {
                    method: 'POST',
                    headers: {
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
                    }
                }).then(function (respuesta) {
                    return respuesta.json().then(function (datos) {
                        return { ok: respuesta.ok, datos: datos };
                    });
                }).then(function (resultado) {
                    if (! resultado.ok) {
                        error.textContent = resultado.datos.message || 'No se pudo generar la planificación.';
                        error.classList.remove('d-none');
                        if (resultado.datos.respuesta) {
                            texto.value = resultado.datos.respuesta;
                            caja.classList.remove('d-none');
                        }
                        boton.disabled = false;
                        cargando.classList.remove('d-flex');
                        cargando.classList.add('d-none');
                        return;
                    }

                    window.location = resultado.datos.url;
                }).catch(function () {
                    error.textContent = 'No se pudo generar la planificación.';
                    error.classList.remove('d-none');
                    boton.disabled = false;
                    cargando.classList.remove('d-flex');
                    cargando.classList.add('d-none');
                }).finally(function () {
                    clearInterval(reloj);
                });
            });

            document.querySelectorAll('.generar-guia').forEach(function (boton) {
                boton.addEventListener('click', function () {
                    var cargando = document.getElementById('cargando-guia');
                    var mensaje = document.getElementById('mensaje-guia');
                    var error = document.getElementById('error-planificacion');
                    var avisos = [
                        'redactando la guía de estudios',
                        'preparando ejemplos y ejercicios',
                        'generando el documento Word'
                    ];
                    var indice = 0;
                    var reloj = setInterval(function () {
                        indice = (indice + 1) % avisos.length;
                        mensaje.textContent = avisos[indice];
                    }, 2500);
                    var acciones = boton.parentElement;

                    boton.disabled = true;
                    error.classList.add('d-none');
                    mensaje.textContent = avisos[0];
                    cargando.classList.remove('d-none');
                    cargando.classList.add('d-flex');

                    fetch(@json(route('asignaturas.guias.generar', $asignatura)), {
                        method: 'POST',
                        headers: {
                            'Accept': 'application/json',
                            'Content-Type': 'application/json',
                            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
                        },
                        body: JSON.stringify({
                            fecha: boton.dataset.fecha,
                            orden: Number(boton.dataset.orden)
                        })
                    }).then(function (respuesta) {
                        return respuesta.text().then(function (texto) {
                            var datos = {};
                            try {
                                datos = texto ? JSON.parse(texto) : {};
                            } catch (e) {
                                datos = { message: 'El servidor cortó la generación. Reinicia Laragon e inténtalo de nuevo.' };
                            }
                            return { ok: respuesta.ok, datos: datos };
                        });
                    }).then(function (resultado) {
                        if (! resultado.ok) {
                            error.textContent = resultado.datos.message || 'No se pudo generar la guía de estudios.';
                            error.classList.remove('d-none');
                            return;
                        }

                        var enlace = acciones.querySelector('.descargar-guia');
                        enlace.href = resultado.datos.url;
                        enlace.classList.remove('d-none');
                        boton.textContent = 'Generar guía de estudios';
                    }).catch(function () {
                        error.textContent = 'No se pudo generar la guía de estudios.';
                        error.classList.remove('d-none');
                    }).finally(function () {
                        clearInterval(reloj);
                        boton.disabled = false;
                        cargando.classList.remove('d-flex');
                        cargando.classList.add('d-none');
                    });
                });
            });
        </script>
    @endpush
</x-app-layout>
