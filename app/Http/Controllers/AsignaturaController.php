<?php

namespace App\Http\Controllers;

use App\Actions\AprenderProgramaModulo;
use App\Actions\GenerarClasesAsignatura;
use App\Actions\GenerarPlanificacion;
use App\Actions\ProcesarPdfAsignatura;
use App\Actions\RespuestaPlanificacionInvalida;
use App\Actions\ResumenActividades;
use App\Http\Requests\PrepararAsignaturaRequest;
use App\Http\Requests\SubirPdfAsignaturaRequest;
use App\Models\Asignatura;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;
use RuntimeException;
use Symfony\Component\HttpFoundation\StreamedResponse;

class AsignaturaController extends Controller
{
    public function __construct(
        private GenerarClasesAsignatura $generarClases,
        private ProcesarPdfAsignatura $procesarPdf,
        private AprenderProgramaModulo $aprenderPrograma,
        private GenerarPlanificacion $generarPlanificacion,
    ) {}

    public function index(): View
    {
        Gate::authorize('viewAny', Asignatura::class);

        $asignaturas = Asignatura::query()
            ->where('user_id', auth()->id())
            ->with(['horarios', 'fechasExcluidas'])
            ->orderBy('nombre')
            ->get();

        return view('asignaturas.index', [
            'asignaturas' => $asignaturas,
        ]);
    }

    public function create(): View
    {
        Gate::authorize('create', Asignatura::class);

        return view('asignaturas.form', [
            'asignatura' => new Asignatura,
        ]);
    }

    public function store(PrepararAsignaturaRequest $request): RedirectResponse
    {
        Gate::authorize('create', Asignatura::class);

        $asignatura = $this->guardar($request, new Asignatura([
            'user_id' => $request->user()->id,
        ]));

        return redirect()
            ->route('asignaturas.show', $asignatura)
            ->with('status', 'Asignatura preparada.');
    }

    public function show(Asignatura $asignatura): View
    {
        Gate::authorize('view', $asignatura);

        $asignatura->load('clases');

        return view('asignaturas.show', [
            'asignatura' => $asignatura,
        ]);
    }

    public function edit(Asignatura $asignatura): View
    {
        Gate::authorize('update', $asignatura);

        $asignatura->load(['horarios', 'fechasExcluidas']);

        return view('asignaturas.form', [
            'asignatura' => $asignatura,
        ]);
    }

    public function update(PrepararAsignaturaRequest $request, Asignatura $asignatura): RedirectResponse
    {
        Gate::authorize('update', $asignatura);

        $this->guardar($request, $asignatura);

        return redirect()
            ->route('asignaturas.show', $asignatura)
            ->with('status', 'Asignatura actualizada.');
    }

    public function documento(Asignatura $asignatura): View
    {
        Gate::authorize('update', $asignatura);

        return view('asignaturas.documento', [
            'asignatura' => $asignatura,
        ]);
    }

    public function subirPdf(SubirPdfAsignaturaRequest $request, Asignatura $asignatura): RedirectResponse
    {
        if ($asignatura->pdf_path) {
            Storage::disk('local')->delete($asignatura->pdf_path);
        }

        $archivo = $request->file('pdf');

        $asignatura->update([
            'pdf_path' => $archivo->store('asignaturas/'.$asignatura->id, 'local'),
            'pdf_nombre' => $archivo->getClientOriginalName(),
        ]);

        return redirect()
            ->route('asignaturas.documento', $asignatura)
            ->with('status', 'PDF cargado.');
    }

    public function procesarPdf(Asignatura $asignatura): JsonResponse
    {
        Gate::authorize('update', $asignatura);

        try {
            $markdown = $this->procesarPdf->handle($asignatura);
        } catch (RuntimeException $exception) {
            return response()->json([
                'message' => $exception->getMessage(),
            ], 422);
        }

        $asignatura->update([
            'markdown' => $markdown,
        ]);

        return response()->json([
            'message' => 'Proceso terminado',
            'markdown' => $markdown,
        ]);
    }

    public function aprenderPrograma(Asignatura $asignatura): JsonResponse
    {
        Gate::authorize('update', $asignatura);

        try {
            $unidades = $this->aprenderPrograma->handle($asignatura);
        } catch (RuntimeException $exception) {
            return response()->json([
                'message' => $exception->getMessage(),
            ], 422);
        }

        $asignatura->update([
            'unidades' => $unidades,
        ]);

        return response()->json([
            'url' => route('asignaturas.unidades', $asignatura),
        ]);
    }

    public function unidades(Asignatura $asignatura): View
    {
        Gate::authorize('view', $asignatura);

        return view('asignaturas.unidades', [
            'asignatura' => $asignatura,
        ]);
    }

    public function actividades(Asignatura $asignatura): View
    {
        Gate::authorize('view', $asignatura);

        $asignatura->load('clases');

        return view('asignaturas.actividades', [
            'asignatura' => $asignatura,
            'resumen' => ResumenActividades::desde($asignatura),
        ]);
    }

    public function generarPlanificacion(Asignatura $asignatura): JsonResponse
    {
        Gate::authorize('update', $asignatura);

        try {
            $planificacion = $this->generarPlanificacion->handle($asignatura);
        } catch (RuntimeException $exception) {
            return response()->json([
                'message' => $exception->getMessage(),
                'respuesta' => $exception instanceof RespuestaPlanificacionInvalida ? $exception->respuesta : null,
            ], 422);
        }

        $asignatura->update([
            'planificacion' => $planificacion,
        ]);

        return response()->json([
            'url' => route('asignaturas.planificacion', $asignatura),
        ]);
    }

    public function planificacion(Asignatura $asignatura): View
    {
        Gate::authorize('view', $asignatura);

        $asignatura->load('clases');
        $plan = $asignatura->planificacion ?? [];
        $bloquesPorFecha = collect($plan['calendario'] ?? [])->keyBy('fecha');
        $fechas = $asignatura->clases
            ->map(fn ($clase): string => $clase->fecha->toDateString())
            ->merge($bloquesPorFecha->keys())
            ->unique()
            ->sort()
            ->values();

        $dias = $fechas->map(function (string $fecha) use ($asignatura, $bloquesPorFecha): array {
            $delDia = $asignatura->clases->filter(
                fn ($clase): bool => $clase->fecha->toDateString() === $fecha
            );

            return [
                'fecha' => $fecha,
                'sin_clase' => $delDia->isNotEmpty() && $delDia->every(fn ($clase): bool => $clase->sin_clase),
                'comentario' => $delDia->pluck('comentario')->filter()->first(),
                'bloques' => $bloquesPorFecha->get($fecha)['bloques'] ?? [],
            ];
        });

        return view('asignaturas.planificacion', [
            'asignatura' => $asignatura,
            'dias' => $dias,
        ]);
    }

    public function descargarPdf(Asignatura $asignatura): StreamedResponse
    {
        Gate::authorize('view', $asignatura);

        abort_unless($asignatura->pdf_path && Storage::disk('local')->exists($asignatura->pdf_path), 404);

        return Storage::disk('local')->download($asignatura->pdf_path, $asignatura->pdf_nombre ?? 'documento.pdf');
    }

    public function destroy(Asignatura $asignatura): RedirectResponse
    {
        Gate::authorize('delete', $asignatura);

        if ($asignatura->pdf_path) {
            Storage::disk('local')->delete($asignatura->pdf_path);
        }

        $asignatura->delete();

        return redirect()
            ->route('asignaturas.index')
            ->with('status', 'Asignatura eliminada.');
    }

    private function guardar(PrepararAsignaturaRequest $request, Asignatura $asignatura): Asignatura
    {
        return DB::transaction(function () use ($request, $asignatura): Asignatura {
            $asignatura->fill($request->safe()->only([
                'nombre',
                'descripcion',
                'fecha_inicio',
                'fecha_termino',
            ]));
            $asignatura->save();

            $asignatura->horarios()->delete();
            $asignatura->fechasExcluidas()->delete();

            $horarios = collect($request->validated('horarios'))->map(fn (array $horario): array => [
                'dia' => $horario['dia'],
                'hora_inicio' => $horario['hora_inicio'],
                'hora_termino' => $horario['hora_termino'],
            ]);

            if ($horarios->isNotEmpty()) {
                $asignatura->horarios()->createMany($horarios->all());
            }

            $fechas = collect($request->validated('fechas_sin_clase'))->map(fn (array $fecha): array => [
                'fecha' => $fecha['fecha'],
                'comentario' => $fecha['comentario'] ?? null,
            ]);

            if ($fechas->isNotEmpty()) {
                $asignatura->fechasExcluidas()->createMany($fechas->all());
            }

            $this->generarClases->handle($asignatura);

            return $asignatura;
        });
    }
}
