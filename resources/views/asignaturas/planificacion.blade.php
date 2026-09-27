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

        <div class="accordion" id="acordeon-planificacion">
            @foreach ($dias as $indice => $dia)
                @php
                    $encabezado = $dia['sin_clase'] ? 'text-danger' : '';
                @endphp
                <div class="card {{ $dia['sin_clase'] ? 'border-danger' : '' }}">
                    <div class="card-header {{ $dia['sin_clase'] ? 'bg-danger-50' : '' }}" id="encabezado-dia-{{ $indice }}">
                        <button class="btn btn-link {{ $encabezado }} {{ $indice === 0 ? '' : 'collapsed' }}" type="button" data-toggle="collapse" data-target="#dia-{{ $indice }}" aria-expanded="{{ $indice === 0 ? 'true' : 'false' }}">
                            {{ \Illuminate\Support\Carbon::parse($dia['fecha'])->format('d/m/Y') }}
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
        </script>
    @endpush
</x-app-layout>
