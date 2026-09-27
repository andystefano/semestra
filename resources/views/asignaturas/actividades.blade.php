<x-app-layout>
    <ol class="breadcrumb page-breadcrumb">
        <li class="breadcrumb-item"><a href="{{ route('dashboard') }}">{{ config('app.name') }}</a></li>
        <li class="breadcrumb-item"><a href="{{ route('asignaturas.index') }}">Asignaturas</a></li>
        <li class="breadcrumb-item"><a href="{{ route('asignaturas.unidades', $asignatura) }}">Unidades</a></li>
        <li class="breadcrumb-item active">Actividades</li>
    </ol>

    <div class="subheader">
        <h1 class="subheader-title">
            <i class="subheader-icon fal fa-tasks"></i> {{ $asignatura->nombre }}
            <small>{{ $resumen->desde }} a {{ $resumen->hasta }}</small>
        </h1>
    </div>

    <div class="row mb-3">
        <div class="col-md-6">
            <div class="p-3 bg-primary-300 rounded text-white">
                <h3 class="m-0">{{ $resumen->clasesTotales }}</h3>
                <span>Clases totales</span>
            </div>
        </div>
        <div class="col-md-6">
            <div class="p-3 bg-info-200 rounded text-white">
                <h3 class="m-0">{{ $resumen->diasDeClase }}</h3>
                <span>Días de clase</span>
            </div>
        </div>
    </div>

    @if ($resumen->diasInsuficientes)
        <div class="alert alert-warning">
            No alcanzan los días de clase para separar la presentación, el día bloqueado de la última unidad y las tres evaluaciones finales.
        </div>
    @endif

    <div class="panel">
        <div class="panel-hdr">
            <h2>Lista <span class="fw-300">actividades</span></h2>
        </div>
        <div class="panel-container show">
            <div class="panel-content">
                <ul class="list-group">
                    <li class="list-group-item">
                        <strong>Primer día completo{{ $resumen->primerDia ? ' · '.$resumen->primerDia : '' }}</strong>
                        <div>Presentación de inicio y evaluación diagnóstica.</div>
                    </li>
                    @for ($numero = 1; $numero <= $resumen->evaluacionesDeUnidad; $numero++)
                        <li class="list-group-item">
                            <strong>Unidad {{ $numero }}</strong>
                            <div>Clases unidad {{ $numero }}.</div>
                        </li>
                        <li class="list-group-item">
                            <strong>Unidad {{ $numero }}{{ $numero === $resumen->evaluacionesDeUnidad && $resumen->diaUltimaUnidad ? ' · '.$resumen->diaUltimaUnidad : '' }}</strong>
                            @if ($numero === $resumen->evaluacionesDeUnidad)
                                <div>Evaluación de 60 minutos. El día completo queda bloqueado: no se enseñan clases y el resto del día queda libre.</div>
                            @else
                                <div>Evaluación de 60 minutos.</div>
                            @endif
                        </li>
                    @endfor
                    <li class="list-group-item">
                        <strong>Antepenúltimo día de clase{{ $resumen->diaRecuperativo ? ' · '.$resumen->diaRecuperativo : '' }}</strong>
                        <div>Prueba recuperativa. El día completo queda bloqueado: no se enseñan clases y el resto del día queda libre.</div>
                    </li>
                    <li class="list-group-item">
                        <strong>Penúltimo día completo{{ $resumen->diaExamen ? ' · '.$resumen->diaExamen : '' }}</strong>
                        <div>Examen 1.</div>
                    </li>
                    <li class="list-group-item">
                        <strong>Último día completo{{ $resumen->diaRepeticion ? ' · '.$resumen->diaRepeticion : '' }}</strong>
                        <div>Examen de repetición.</div>
                    </li>
                </ul>
            </div>
        </div>
    </div>

    <div id="error-planificacion" class="alert alert-danger d-none mt-3"></div>
    <div id="respuesta-planificacion" class="d-none mt-3">
        <label class="form-label" for="texto-respuesta-planificacion">Respuesta recibida. Puedes leerla y copiarla.</label>
        <textarea id="texto-respuesta-planificacion" class="form-control" rows="16" readonly></textarea>
    </div>

    <div class="d-flex justify-content-end gap-2 mt-3">
        @if (is_array($asignatura->planificacion))
            <a href="{{ route('asignaturas.planificacion', $asignatura) }}" class="btn btn-outline-primary">Ver planificación</a>
        @endif
        <button type="button" id="generar-planificacion" class="btn btn-primary">
            {{ is_array($asignatura->planificacion) ? 'Generar de nuevo' : 'Generar Planificación' }}
        </button>
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
                    var respuesta = document.getElementById('respuesta-planificacion');
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
                    respuesta.classList.add('d-none');
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
                                respuesta.classList.remove('d-none');
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
