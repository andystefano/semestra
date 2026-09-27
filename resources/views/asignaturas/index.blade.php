<x-app-layout>
    <ol class="breadcrumb page-breadcrumb">
        <li class="breadcrumb-item"><a href="{{ route('dashboard') }}">{{ config('app.name') }}</a></li>
        <li class="breadcrumb-item">Asignaturas</li>
        <li class="breadcrumb-item active">Listado</li>
    </ol>

    <div class="subheader">
        <h1 class="subheader-title">
            <i class="subheader-icon fal fa-book"></i> Listado de <span class="fw-300">asignaturas</span>
        </h1>
        <div class="subheader-block">
            <a href="{{ route('asignaturas.create') }}" class="btn btn-primary">Preparar asignatura</a>
        </div>
    </div>

    @if (session('status'))
        <div class="alert alert-success">{{ session('status') }}</div>
    @endif

    <div class="row">
        <div class="col-xl-12">
            <div class="panel">
                <div class="panel-hdr">
                    <h2>Asignaturas</h2>
                </div>
                <div class="panel-container show">
                    <div class="panel-content">
                        @if ($asignaturas->isEmpty())
                            <p class="mb-0">Todavía no hay asignaturas. Puedes preparar la primera desde el menú.</p>
                        @else
                            <div class="table-responsive">
                                <table class="table table-bordered table-hover mb-0">
                                    <thead>
                                        <tr>
                                            <th>Nombre</th>
                                            <th>Período</th>
                                            <th>Horario semanal</th>
                                            <th>Sin clases</th>
                                            <th></th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @foreach ($asignaturas as $asignatura)
                                            <tr>
                                                <td>
                                                    <a href="{{ route('asignaturas.show', $asignatura) }}" class="fw-500">{{ $asignatura->nombre }}</a>
                                                    @if ($asignatura->descripcion)
                                                        <div class="text-muted small">{{ $asignatura->descripcion }}</div>
                                                    @endif
                                                </td>
                                                <td>{{ $asignatura->fecha_inicio->format('d/m/Y') }} – {{ $asignatura->fecha_termino->format('d/m/Y') }}</td>
                                                <td>
                                                    @forelse ($asignatura->horarios as $horario)
                                                        <div>{{ $horario->rango() }}</div>
                                                    @empty
                                                        <span class="text-muted">Sin horario</span>
                                                    @endforelse
                                                </td>
                                                <td>
                                                    @forelse ($asignatura->fechasExcluidas as $excluida)
                                                        <div>{{ $excluida->fecha->format('d/m/Y') }}</div>
                                                    @empty
                                                        <span class="text-muted">Ninguna</span>
                                                    @endforelse
                                                </td>
                                                <td class="text-right text-nowrap">
                                                    <a href="{{ route('asignaturas.edit', $asignatura) }}" class="btn btn-sm btn-outline-primary">Editar</a>
                                                    <form method="POST" action="{{ route('asignaturas.destroy', $asignatura) }}" class="d-inline" onsubmit="return confirm('¿Eliminar esta asignatura?')">
                                                        @csrf
                                                        @method('DELETE')
                                                        <button type="submit" class="btn btn-sm btn-outline-danger">Eliminar</button>
                                                    </form>
                                                </td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                        @endif
                    </div>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
