<?php

namespace App\Presentacion;

use App\Models\Asignatura;
use Illuminate\Support\Carbon;

class FechasPlanificacion
{
    /**
     * @return list<array{fecha: string, etiqueta: string, detalle: string}>
     */
    public function filas(Asignatura $asignatura): array
    {
        $asignatura->loadMissing(['clases', 'fechasExcluidas']);
        $filas = [
            [
                'fecha' => $asignatura->fecha_inicio->format('d/m/Y'),
                'etiqueta' => 'Inicio',
                'detalle' => '',
                'orden' => $asignatura->fecha_inicio->toDateString().'-0',
            ],
            [
                'fecha' => $asignatura->fecha_termino->format('d/m/Y'),
                'etiqueta' => 'Fin',
                'detalle' => '',
                'orden' => $asignatura->fecha_termino->toDateString().'-9',
            ],
        ];

        foreach ($asignatura->fechasExcluidas as $excluida) {
            $filas[] = $this->feriado($excluida->fecha->toDateString(), (string) ($excluida->comentario ?? ''));
        }

        foreach ($asignatura->clases as $clase) {
            if (! $clase->sin_clase) {
                continue;
            }

            $clave = $clase->fecha->toDateString();

            if (collect($filas)->contains(fn (array $fila): bool => str_starts_with($fila['orden'], $clave) && $fila['etiqueta'] === 'Feriado')) {
                continue;
            }

            $filas[] = $this->feriado($clave, (string) ($clase->comentario ?? ''));
        }

        foreach ($asignatura->planificacion['calendario'] ?? [] as $jornada) {
            foreach ($jornada['bloques'] ?? [] as $bloque) {
                $tipo = (string) ($bloque['tipo'] ?? '');

                if ($tipo === '' || $tipo === 'CLASE') {
                    continue;
                }

                $fecha = (string) ($jornada['fecha'] ?? '');

                $filas[] = [
                    'fecha' => Carbon::parse($fecha)->format('d/m/Y'),
                    'etiqueta' => $this->etiqueta($tipo),
                    'detalle' => trim((string) ($bloque['descripcion'] ?? '')),
                    'orden' => $fecha.'-5',
                ];
            }
        }

        usort($filas, fn (array $a, array $b): int => $a['orden'] <=> $b['orden']);

        return array_map(fn (array $fila): array => [
            'fecha' => $fila['fecha'],
            'etiqueta' => $fila['etiqueta'],
            'detalle' => $fila['detalle'],
        ], $filas);
    }

    /**
     * @return array{fecha: string, etiqueta: string, detalle: string, orden: string}
     */
    private function feriado(string $fecha, string $comentario): array
    {
        return [
            'fecha' => Carbon::parse($fecha)->format('d/m/Y'),
            'etiqueta' => 'Feriado',
            'detalle' => $comentario,
            'orden' => $fecha.'-1',
        ];
    }

    private function etiqueta(string $tipo): string
    {
        return match ($tipo) {
            'EVALUACION' => 'Evaluación',
            'EVALUACION_RECUPERATIVA' => 'Evaluación recuperativa',
            'EXAMEN_1' => 'Examen 1',
            'EXAMEN_REPETICION' => 'Examen de repetición',
            default => $tipo,
        };
    }
}
