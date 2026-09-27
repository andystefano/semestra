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
</x-app-layout>
