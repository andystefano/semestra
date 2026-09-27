<x-app-layout>
    <ol class="breadcrumb page-breadcrumb">
        <li class="breadcrumb-item"><a href="{{ route('dashboard') }}">{{ config('app.name') }}</a></li>
        <li class="breadcrumb-item"><a href="{{ route('asignaturas.index') }}">Asignaturas</a></li>
        <li class="breadcrumb-item"><a href="{{ route('asignaturas.show', $asignatura) }}">{{ $asignatura->nombre }}</a></li>
        <li class="breadcrumb-item active">Unidades</li>
    </ol>

    <div class="subheader">
        <h1 class="subheader-title">
            <i class="subheader-icon fal fa-layer-group"></i> {{ $asignatura->nombre }}
            <small>{{ $asignatura->unidades['cantidad'] ?? 0 }} unidades confirmadas desde el plan</small>
        </h1>
    </div>

    @php
        $unidades = $asignatura->unidades['unidades'] ?? [];
    @endphp

    @if ($unidades === [])
        <div class="alert alert-warning">Todavía no hay unidades guardadas.</div>
    @else
        <div class="accordion" id="acordeon-unidades">
            @foreach ($unidades as $indice => $unidad)
                <div class="card">
                    <div class="card-header" id="encabezado-unidad-{{ $indice }}">
                        <button class="btn btn-link {{ $indice === 0 ? '' : 'collapsed' }}" type="button" data-toggle="collapse" data-target="#unidad-{{ $indice }}" aria-expanded="{{ $indice === 0 ? 'true' : 'false' }}">
                            Unidad {{ $unidad['numero'] ?? $indice + 1 }}@if (! empty($unidad['nombre'])): {{ $unidad['nombre'] }}@endif
                        </button>
                    </div>
                    <div id="unidad-{{ $indice }}" class="collapse {{ $indice === 0 ? 'show' : '' }}" data-parent="#acordeon-unidades">
                        <div class="card-body">
                            <p><strong>Horas sugeridas</strong><br>{{ $unidad['horas_sugeridas'] ?? '—' }}</p>
                            <p><strong>Aprendizaje esperado</strong><br>{{ $unidad['aprendizaje_esperado'] ?: '—' }}</p>
                            <p><strong>Tipo de habilidad asociada al AE</strong><br>{{ $unidad['tipo_habilidad'] ?: '—' }}</p>
                            <p><strong>Competencias personales, sociales y valóricas</strong><br>{{ $unidad['competencias_personales_sociales_valoricas'] ?: '—' }}</p>
                            <p class="mb-1"><strong>Criterios de evaluación</strong></p>
                            @if (($unidad['criterios_evaluacion'] ?? []) === [])
                                <p>—</p>
                            @else
                                <ul>
                                    @foreach ($unidad['criterios_evaluacion'] as $criterio)
                                        <li>{{ $criterio }}</li>
                                    @endforeach
                                </ul>
                            @endif
                            <p class="mb-1"><strong>Contenidos obligatorios</strong></p>
                            @if (($unidad['contenidos_obligatorios'] ?? []) === [])
                                <p class="mb-0">—</p>
                            @else
                                <ul class="mb-0">
                                    @foreach ($unidad['contenidos_obligatorios'] as $contenido)
                                        <li>{{ $contenido }}</li>
                                    @endforeach
                                </ul>
                            @endif
                        </div>
                    </div>
                </div>
            @endforeach
        </div>
    @endif

    <div class="d-flex justify-content-end mt-3">
        <a href="{{ route('asignaturas.actividades', $asignatura) }}" class="btn btn-primary">Continuar con actividades</a>
    </div>
</x-app-layout>
