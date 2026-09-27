<?php

namespace App\Actions;

use App\DiaSemana;
use App\Models\Asignatura;
use App\Models\AsignaturaClase;
use App\Models\AsignaturaHorario;
use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;

class GenerarClasesAsignatura
{
    public function handle(Asignatura $asignatura): void
    {
        $asignatura->load(['horarios', 'fechasExcluidas']);

        $excluidas = $asignatura->fechasExcluidas->keyBy(
            fn ($excluida): string => $excluida->fecha->toDateString()
        );

        $ocurrencias = [];
        $fecha = $asignatura->fecha_inicio->copy()->startOfDay();
        $termino = $asignatura->fecha_termino->copy()->startOfDay();

        while ($fecha->lte($termino)) {
            foreach ($asignatura->horarios as $horario) {
                if ($horario->dia !== DiaSemana::from($fecha->dayOfWeekIso)) {
                    continue;
                }

                $clave = $fecha->toDateString();
                $excluida = $excluidas->get($clave);

                $ocurrencias[] = $this->ocurrencia(
                    $fecha,
                    $horario,
                    $excluida !== null,
                    $excluida?->comentario,
                );
            }

            $fecha->addDay();
        }

        $fechasCubiertas = collect($ocurrencias)->pluck('fecha');

        foreach ($excluidas as $clave => $excluida) {
            if ($fechasCubiertas->contains($clave)) {
                continue;
            }

            $ocurrencias[] = [
                'fecha' => $clave,
                'hora_inicio' => null,
                'hora_termino' => null,
                'horas_cronologicas' => null,
                'horas_pedagogicas' => null,
                'sin_clase' => true,
                'comentario' => $excluida->comentario,
            ];
        }

        usort($ocurrencias, function (array $primera, array $segunda): int {
            return [$primera['fecha'], $primera['hora_inicio'] ?? '']
                <=> [$segunda['fecha'], $segunda['hora_inicio'] ?? ''];
        });

        $numero = 1;

        foreach ($ocurrencias as $indice => $ocurrencia) {
            $ocurrencias[$indice]['numero'] = $ocurrencia['sin_clase'] ? null : $numero++;
        }

        $asignatura->clases()->delete();

        if ($ocurrencias !== []) {
            $asignatura->clases()->createMany($ocurrencias);
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function ocurrencia(CarbonInterface $fecha, AsignaturaHorario $horario, bool $sinClase, ?string $comentario): array
    {
        $minutos = $this->minutos((string) $horario->hora_inicio, (string) $horario->hora_termino);

        return [
            'fecha' => $fecha->toDateString(),
            'hora_inicio' => substr((string) $horario->hora_inicio, 0, 5),
            'hora_termino' => substr((string) $horario->hora_termino, 0, 5),
            'horas_cronologicas' => number_format($minutos / 60, 2, '.', ''),
            'horas_pedagogicas' => number_format($minutos / AsignaturaClase::MINUTOS_HORA_PEDAGOGICA, 2, '.', ''),
            'sin_clase' => $sinClase,
            'comentario' => $sinClase ? $comentario : null,
        ];
    }

    private function minutos(string $inicio, string $termino): int
    {
        $inicio = Carbon::createFromFormat('H:i', substr($inicio, 0, 5));
        $termino = Carbon::createFromFormat('H:i', substr($termino, 0, 5));

        return ($termino->hour * 60 + $termino->minute) - ($inicio->hour * 60 + $inicio->minute);
    }
}
