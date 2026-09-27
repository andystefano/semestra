<?php

namespace App\Actions;

use App\Models\Asignatura;
use Illuminate\Support\Carbon;

class ResumenActividades
{
    public function __construct(
        public readonly string $desde,
        public readonly string $hasta,
        public readonly int $clasesTotales,
        public readonly int $diasDeClase,
        public readonly ?string $primerDia,
        public readonly ?string $isoPrimerDia,
        public readonly ?string $diaUltimaUnidad,
        public readonly ?string $isoDiaUltimaUnidad,
        public readonly ?string $diaRecuperativo,
        public readonly ?string $isoDiaRecuperativo,
        public readonly ?string $diaExamen,
        public readonly ?string $isoDiaExamen,
        public readonly ?string $diaRepeticion,
        public readonly ?string $isoDiaRepeticion,
        public readonly int $evaluacionesDeUnidad,
        public readonly bool $diasInsuficientes,
    ) {}

    public static function desde(Asignatura $asignatura): self
    {
        $clases = $asignatura->clases->where('sin_clase', false);

        $dias = $clases
            ->map(fn ($clase): string => $clase->fecha->toDateString())
            ->unique()
            ->sort()
            ->values();

        $cantidad = $dias->count();
        $evaluaciones = (int) ($asignatura->unidades['cantidad'] ?? count($asignatura->unidades['unidades'] ?? []));
        $mostrar = fn (?string $fecha): ?string => $fecha === null
            ? null
            : Carbon::parse($fecha)->format('d/m/Y');

        return new self(
            desde: $asignatura->fecha_inicio->format('d/m/Y'),
            hasta: $asignatura->fecha_termino->format('d/m/Y'),
            clasesTotales: $clases->count(),
            diasDeClase: $cantidad,
            primerDia: $mostrar($dias->get(0)),
            isoPrimerDia: $dias->get(0),
            diaUltimaUnidad: $mostrar($ultima = $evaluaciones > 0 && $cantidad >= 5 ? $dias->get($cantidad - 4) : null),
            isoDiaUltimaUnidad: $ultima,
            diaRecuperativo: $mostrar($recuperativo = $cantidad >= 3 ? $dias->get($cantidad - 3) : null),
            isoDiaRecuperativo: $recuperativo,
            diaExamen: $mostrar($examen = $cantidad >= 2 ? $dias->get($cantidad - 2) : null),
            isoDiaExamen: $examen,
            diaRepeticion: $mostrar($repeticion = $cantidad >= 1 ? $dias->get($cantidad - 1) : null),
            isoDiaRepeticion: $repeticion,
            evaluacionesDeUnidad: $evaluaciones,
            diasInsuficientes: $evaluaciones > 0 ? $cantidad < 5 : $cantidad < 4,
        );
    }
}
