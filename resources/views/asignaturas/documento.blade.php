<x-app-layout>
    <ol class="breadcrumb page-breadcrumb">
        <li class="breadcrumb-item"><a href="{{ route('dashboard') }}">{{ config('app.name') }}</a></li>
        <li class="breadcrumb-item"><a href="{{ route('asignaturas.index') }}">Asignaturas</a></li>
        <li class="breadcrumb-item"><a href="{{ route('asignaturas.show', $asignatura) }}">{{ $asignatura->nombre }}</a></li>
        <li class="breadcrumb-item active">PDF</li>
    </ol>

    <div class="subheader">
        <h1 class="subheader-title">
            <i class="subheader-icon fal fa-file-pdf"></i> {{ $asignatura->nombre }}
            <small>Paso siguiente: cargar un archivo PDF</small>
        </h1>
    </div>

    @if (session('status'))
        <div class="alert alert-success">{{ session('status') }}</div>
    @endif

    <div class="panel">
        <div class="panel-hdr">
            <h2>Cargar <span class="fw-300">PDF</span></h2>
        </div>
        <div class="panel-container show">
            <div class="panel-content">
                @if ($asignatura->pdf_nombre)
                    <p>
                        Archivo actual:
                        <a href="{{ route('asignaturas.documento.descargar', $asignatura) }}">{{ $asignatura->pdf_nombre }}</a>
                    </p>
                @endif

                <form method="POST" action="{{ route('asignaturas.documento.store', $asignatura) }}" enctype="multipart/form-data">
                    @csrf
                    <div class="form-group">
                        <label class="form-label" for="pdf">Archivo PDF</label>
                        <input type="file" id="pdf" name="pdf" accept="application/pdf,.pdf" class="form-control-file @error('pdf') is-invalid @enderror" required>
                        @error('pdf')
                            <div class="invalid-feedback d-block">{{ $message }}</div>
                        @enderror
                        <small class="form-text text-muted">Tamaño máximo: 10 MB.</small>
                    </div>
                    <button type="submit" class="btn btn-primary">Cargar PDF</button>
                    <a href="{{ route('asignaturas.show', $asignatura) }}" class="btn btn-outline-secondary">Volver</a>
                </form>
            </div>
        </div>
    </div>

    <div class="panel">
        <div class="panel-hdr">
            <h2>Próximo <span class="fw-300">paso</span></h2>
        </div>
        <div class="panel-container show">
            <div class="panel-content">
                <p class="mb-3">Convertir el PDF a Markdown con LlamaParse.</p>

                <div id="estado-proceso" class="alert alert-success {{ $asignatura->markdown === null ? 'd-none' : '' }}">
                    Proceso terminado
                </div>
                <div id="error-proceso" class="alert alert-danger d-none"></div>

                <button type="button" id="procesar-archivo" class="btn btn-primary" @disabled(! $asignatura->pdf_path)>
                    Procesar archivo
                </button>
                <button type="button" id="ver-informacion" class="btn btn-outline-primary {{ $asignatura->markdown === null ? 'd-none' : '' }}" data-toggle="modal" data-target="#modal-markdown">
                    Ver información
                </button>

                <div class="mt-3">
                    <button type="button" id="aprender-programa" class="btn btn-success" @disabled($asignatura->markdown === null)>
                        Aprender a partir de programa del módulo
                    </button>
                    @if (is_array($asignatura->unidades))
                        <a href="{{ route('asignaturas.unidades', $asignatura) }}" class="btn btn-outline-success">Ver unidades</a>
                    @endif
                </div>
            </div>
        </div>
    </div>

    <div class="modal fade" id="modal-markdown" tabindex="-1" role="dialog" aria-hidden="true">
        <div class="modal-dialog modal-lg" role="document">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">{{ $asignatura->nombre }}</h5>
                    <button type="button" class="close" data-dismiss="modal" aria-label="Cerrar">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <div class="modal-body" style="max-height: 70vh; overflow: auto;">
                    <pre id="markdown-contenido" class="mb-0" style="white-space: pre-wrap;">{{ $asignatura->markdown }}</pre>
                </div>
            </div>
        </div>
    </div>

    <div id="cargando-proceso" class="d-none align-items-center justify-content-center" style="position: fixed; inset: 0; background: rgba(0, 0, 0, .45); z-index: 2000;">
        <div class="text-center text-white">
            <div class="spinner-border" role="status"></div>
            <div id="mensaje-carga" class="mt-3">Procesando archivo…</div>
        </div>
    </div>

    @push('scripts')
        <script>
            document.getElementById('procesar-archivo').addEventListener('click', function () {
                var boton = this;
                var cargando = document.getElementById('cargando-proceso');
                var estado = document.getElementById('estado-proceso');
                var error = document.getElementById('error-proceso');
                var ver = document.getElementById('ver-informacion');

                boton.disabled = true;
                estado.classList.add('d-none');
                error.classList.add('d-none');
                cargando.classList.remove('d-none');
                cargando.classList.add('d-flex');

                fetch(@json(route('asignaturas.documento.procesar', $asignatura)), {
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
                        error.textContent = resultado.datos.message || 'No se pudo procesar el archivo.';
                        error.classList.remove('d-none');
                        boton.disabled = false;
                        return;
                    }

                    document.getElementById('markdown-contenido').textContent = resultado.datos.markdown || '';
                    estado.classList.remove('d-none');
                    ver.classList.remove('d-none');
                    document.getElementById('aprender-programa').disabled = false;
                    boton.disabled = false;
                }).catch(function () {
                    error.textContent = 'No se pudo procesar el archivo.';
                    error.classList.remove('d-none');
                    boton.disabled = false;
                }).finally(function () {
                    cargando.classList.remove('d-flex');
                    cargando.classList.add('d-none');
                });
            });

            document.getElementById('aprender-programa').addEventListener('click', function () {
                var boton = this;
                var cargando = document.getElementById('cargando-proceso');
                var mensaje = document.getElementById('mensaje-carga');
                var error = document.getElementById('error-proceso');
                var avisos = [
                    'Extrayendo Unidades',
                    'aprendiendo CONTENIDOS OBLIGATORIOS',
                    'aprendiendo CRITERIOS DE EVALUACIÓN'
                ];
                var indice = 0;
                var reloj = setInterval(function () {
                    indice = (indice + 1) % avisos.length;
                    mensaje.textContent = avisos[indice];
                }, 2500);

                boton.disabled = true;
                error.classList.add('d-none');
                mensaje.textContent = avisos[0];
                cargando.classList.remove('d-none');
                cargando.classList.add('d-flex');

                fetch(@json(route('asignaturas.documento.aprender', $asignatura)), {
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
                        error.textContent = resultado.datos.message || 'No se pudo aprender el programa.';
                        error.classList.remove('d-none');
                        boton.disabled = false;
                        return;
                    }

                    window.location = resultado.datos.url;
                }).catch(function () {
                    error.textContent = 'No se pudo aprender el programa.';
                    error.classList.remove('d-none');
                    boton.disabled = false;
                }).finally(function () {
                    clearInterval(reloj);
                    if (document.getElementById('aprender-programa').disabled === false) {
                        cargando.classList.remove('d-flex');
                        cargando.classList.add('d-none');
                        mensaje.textContent = 'Procesando archivo…';
                    }
                });
            });
        </script>
    @endpush
</x-app-layout>
