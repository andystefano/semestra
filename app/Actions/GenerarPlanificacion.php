<?php

namespace App\Actions;

use App\Models\Asignatura;
use App\Models\AsignaturaClase;
use RuntimeException;

class GenerarPlanificacion
{
    /**
     * @return array<string, mixed>
     */
    public function handle(Asignatura $asignatura): array
    {
        $asignatura->load(['clases']);
        $encargo = $this->encargo($asignatura);

        if ($encargo['jornadas'] === []) {
            throw new RuntimeException('No hay fechas de clase para planificar.');
        }

        if ($encargo['unidades'] === []) {
            throw new RuntimeException('Primero guarda las unidades del programa.');
        }

        $calendario = $this->asignarContenidos(
            $this->armarActividades($encargo),
            $encargo,
        );

        return [
            'calendario' => $calendario,
            'advertencias' => [],
            'resumen' => [
                'horas_sugeridas_minutos' => $encargo['horas_sugeridas_minutos'],
                'minutos_disponibles_para_clases' => $encargo['minutos_disponibles_para_clases'],
                'factor_proporcional' => $encargo['factor_proporcional'],
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function encargo(Asignatura $asignatura): array
    {
        $asignatura->loadMissing('clases');
        $resumen = ResumenActividades::desde($asignatura);
        $unidades = $asignatura->unidades['unidades'] ?? [];
        $bloqueadas = array_values(array_filter([
            $resumen->isoDiaUltimaUnidad,
            $resumen->isoDiaRecuperativo,
            $resumen->isoDiaExamen,
            $resumen->isoDiaRepeticion,
        ]));
        $jornadas = $this->jornadas($asignatura, $bloqueadas);
        $minutosDisponibles = 0;

        foreach ($jornadas as $jornada) {
            if (in_array($jornada['fecha'], $bloqueadas, true)) {
                continue;
            }

            $minutosDisponibles += $jornada['duracion_minutos'];
        }

        $evaluacionesSueltas = max(0, $resumen->evaluacionesDeUnidad - ($resumen->isoDiaUltimaUnidad ? 1 : 0));
        $minutosDisponibles = max(0, $minutosDisponibles - ($evaluacionesSueltas * 60));
        $minutosSugeridos = 0;

        foreach ($unidades as $unidad) {
            $minutosSugeridos += $this->minutosPedagogicos($unidad['horas_sugeridas'] ?? 0);
        }

        $factor = $minutosSugeridos > 0 ? min(1, $minutosDisponibles / $minutosSugeridos) : 0;
        $unidadesPreparadas = array_map(function (array $unidad, int $indice) use ($factor): array {
            $sugeridos = $this->minutosPedagogicos($unidad['horas_sugeridas'] ?? 0);

            return [
                'numero' => $indice + 1,
                'nombre' => $unidad['nombre'] ?? '',
                'horas_sugeridas' => $unidad['horas_sugeridas'] ?? 0,
                'minutos_asignados' => (int) round($sugeridos * $factor),
                'aprendizaje_esperado' => $unidad['aprendizaje_esperado'] ?? '',
                'criterios_evaluacion' => $unidad['criterios_evaluacion'] ?? [],
                'contenidos_obligatorios' => $unidad['contenidos_obligatorios'] ?? [],
                'tipo_habilidad' => $unidad['tipo_habilidad'] ?? '',
                'competencias_personales_sociales_valoricas' => $unidad['competencias_personales_sociales_valoricas'] ?? '',
            ];
        }, array_values($unidades), array_keys(array_values($unidades)));
        $jornadas = $this->marcarUnidadesPorFecha($jornadas, $unidadesPreparadas);

        return [
            'periodo' => [
                'desde' => $asignatura->fecha_inicio->toDateString(),
                'hasta' => $asignatura->fecha_termino->toDateString(),
            ],
            'minutos_pedagogicos_por_hora' => AsignaturaClase::MINUTOS_HORA_PEDAGOGICA,
            'horas_sugeridas_minutos' => $minutosSugeridos,
            'minutos_disponibles_para_clases' => $minutosDisponibles,
            'factor_proporcional' => round($factor, 4),
            'jornadas' => $jornadas,
            'unidades' => $unidadesPreparadas,
            'restricciones' => array_values(array_filter([
                $resumen->isoPrimerDia ? [
                    'fecha' => $resumen->isoPrimerDia,
                    'tipo' => 'CLASE',
                    'descripcion' => 'Presentación de inicio y evaluación diagnóstica',
                    'dia_completo_bloqueado' => false,
                ] : null,
                $resumen->isoDiaUltimaUnidad ? [
                    'fecha' => $resumen->isoDiaUltimaUnidad,
                    'tipo' => 'EVALUACION',
                    'unidad' => $resumen->evaluacionesDeUnidad,
                    'duracion_minutos' => 60,
                    'dia_completo_bloqueado' => true,
                ] : null,
                $resumen->isoDiaRecuperativo ? [
                    'fecha' => $resumen->isoDiaRecuperativo,
                    'tipo' => 'EVALUACION_RECUPERATIVA',
                    'dia_completo_bloqueado' => true,
                ] : null,
                $resumen->isoDiaExamen ? [
                    'fecha' => $resumen->isoDiaExamen,
                    'tipo' => 'EXAMEN_1',
                    'dia_completo_bloqueado' => true,
                ] : null,
                $resumen->isoDiaRepeticion ? [
                    'fecha' => $resumen->isoDiaRepeticion,
                    'tipo' => 'EXAMEN_REPETICION',
                    'dia_completo_bloqueado' => true,
                ] : null,
            ])),
        ];
    }

    /**
     * @param  array<string, mixed>  $encargo
     * @return list<array<string, mixed>>
     */
    private function armarActividades(array $encargo): array
    {
        $porFecha = [];

        foreach ($encargo['jornadas'] as $jornada) {
            $porFecha[$jornada['fecha']] = [
                'fecha' => $jornada['fecha'],
                'duracion' => (int) $jornada['duracion_minutos'],
                'bloques' => [],
            ];
        }

        $primer = null;

        foreach ($encargo['restricciones'] as $restriccion) {
            if (! ($restriccion['dia_completo_bloqueado'] ?? false)) {
                $primer = $restriccion['fecha'];

                continue;
            }

            if (! isset($porFecha[$restriccion['fecha']])) {
                continue;
            }

            $porFecha[$restriccion['fecha']]['bloques'][] = $this->bloqueEvaluacion(
                (string) $restriccion['tipo'],
                (int) ($restriccion['duracion_minutos'] ?? 60),
                isset($restriccion['unidad']) ? (int) $restriccion['unidad'] : null,
            );
        }

        if ($primer !== null && isset($porFecha[$primer])) {
            $porFecha[$primer]['bloques'][] = [
                'orden' => 1,
                'tipo' => 'CLASE',
                'duracion_minutos' => $porFecha[$primer]['duracion'],
                'descripcion' => 'Presentación de inicio y evaluación diagnóstica',
                'contenidos_obligatorios' => [],
            ];
        }

        $cola = [];

        foreach ($encargo['jornadas'] as $jornada) {
            if ((int) $jornada['minutos_disponibles_para_clases'] <= 0 || $jornada['fecha'] === $primer) {
                continue;
            }

            $cola[] = [
                'fecha' => $jornada['fecha'],
                'restante' => (int) $jornada['duracion_minutos'],
            ];
        }

        $unidades = $encargo['unidades'];
        $cupos = $this->cuposDeUnidad($unidades, $cola);
        $cursor = 0;
        $ultima = $unidades === [] ? 0 : (int) $unidades[array_key_last($unidades)]['numero'];

        foreach ($unidades as $unidad) {
            $numero = (int) $unidad['numero'];
            $pendiente = $cupos[$numero] ?? 0;
            $tuvoClase = false;

            while ($pendiente > 0 && $cursor < count($cola)) {
                if ($cola[$cursor]['restante'] <= 0) {
                    $cursor++;

                    continue;
                }

                $toma = min($cola[$cursor]['restante'], $pendiente);
                $this->agregarBloque($porFecha, $cola[$cursor]['fecha'], [
                    'tipo' => 'CLASE',
                    'duracion_minutos' => $toma,
                    'unidad' => $numero,
                    'descripcion' => '',
                    'contenidos_obligatorios' => [],
                ]);
                $cola[$cursor]['restante'] -= $toma;
                $pendiente -= $toma;
                $tuvoClase = true;

                if ($pendiente <= 0) {
                    $cursor++;

                    break;
                }

                if ($cola[$cursor]['restante'] <= 0) {
                    $cursor++;
                }
            }

            if (! $tuvoClase || $numero === $ultima || $cursor >= count($cola)) {
                continue;
            }

            $evaluacion = min(60, $cola[$cursor]['restante']);

            if ($evaluacion <= 0) {
                continue;
            }

            $this->agregarBloque($porFecha, $cola[$cursor]['fecha'], $this->bloqueEvaluacion('EVALUACION', $evaluacion, $numero));
            $cola[$cursor]['restante'] -= $evaluacion;

            if ($cola[$cursor]['restante'] <= 0) {
                $cursor++;
            }
        }

        $calendario = [];

        foreach ($encargo['jornadas'] as $jornada) {
            $bloques = $porFecha[$jornada['fecha']]['bloques'];

            foreach ($bloques as $indice => &$bloque) {
                $bloque['orden'] = $indice + 1;
            }
            unset($bloque);

            $calendario[] = [
                'fecha' => $jornada['fecha'],
                'bloques' => $bloques,
            ];
        }

        return $calendario;
    }

    /**
     * @param  list<array<string, mixed>>  $calendario
     * @param  array<string, mixed>  $encargo
     * @return list<array<string, mixed>>
     */
    private function asignarContenidos(array $calendario, array $encargo): array
    {
        foreach ($encargo['unidades'] as $unidad) {
            $numero = (int) $unidad['numero'];
            $clases = [];
            $total = 0;

            foreach ($calendario as $indiceFecha => $jornada) {
                foreach ($jornada['bloques'] as $indiceBloque => $bloque) {
                    if (($bloque['tipo'] ?? '') !== 'CLASE' || (int) ($bloque['unidad'] ?? 0) !== $numero) {
                        continue;
                    }

                    $clases[] = [$indiceFecha, $indiceBloque];
                    $total += (int) $bloque['duracion_minutos'];
                }
            }

            if ($clases === []) {
                continue;
            }

            $piezas = $this->repartirNombres($this->textos($unidad['contenidos_obligatorios'] ?? []), $total);
            $cursor = 0;
            $gastado = 0;

            foreach ($clases as [$indiceFecha, $indiceBloque]) {
                $capacidad = (int) $calendario[$indiceFecha]['bloques'][$indiceBloque]['duracion_minutos'];
                $contenidos = [];
                $queda = $capacidad;

                while ($queda > 0 && $cursor < count($piezas)) {
                    $falta = $piezas[$cursor]['minutos'] - $gastado;

                    if ($falta <= 0) {
                        $cursor++;
                        $gastado = 0;

                        continue;
                    }

                    $toma = min($queda, $falta);
                    $contenidos[] = [
                        'nombre' => $piezas[$cursor]['nombre'],
                        'minutos_asignados' => $toma,
                    ];
                    $queda -= $toma;
                    $gastado += $toma;

                    if ($gastado >= $piezas[$cursor]['minutos']) {
                        $cursor++;
                        $gastado = 0;
                    }
                }

                $calendario[$indiceFecha]['bloques'][$indiceBloque]['aprendizaje_esperado'] = (string) ($unidad['aprendizaje_esperado'] ?? '');
                $calendario[$indiceFecha]['bloques'][$indiceBloque]['criterios_evaluacion'] = $this->textos($unidad['criterios_evaluacion'] ?? []);
                $calendario[$indiceFecha]['bloques'][$indiceBloque]['contenidos_obligatorios'] = $contenidos;
                $calendario[$indiceFecha]['bloques'][$indiceBloque]['tipo_habilidad'] = (string) ($unidad['tipo_habilidad'] ?? '');
                $calendario[$indiceFecha]['bloques'][$indiceBloque]['competencias_personales_sociales_valoricas'] = (string) ($unidad['competencias_personales_sociales_valoricas'] ?? '');
            }

            if ($cursor < count($piezas)) {
                $ultimaClase = $clases[array_key_last($clases)];

                while ($cursor < count($piezas)) {
                    $calendario[$ultimaClase[0]]['bloques'][$ultimaClase[1]]['contenidos_obligatorios'][] = [
                        'nombre' => $piezas[$cursor]['nombre'],
                        'minutos_asignados' => max(0, $piezas[$cursor]['minutos'] - $gastado),
                    ];
                    $cursor++;
                    $gastado = 0;
                }
            }
        }

        return $calendario;
    }

    /**
     * @param  list<array<string, mixed>>  $unidades
     * @param  list<array{fecha: string, restante: int}>  $cola
     * @return array<int, int>
     */
    private function cuposDeUnidad(array $unidades, array $cola): array
    {
        $pool = max(0, array_sum(array_column($cola, 'restante')) - (max(0, count($unidades) - 1) * 60));

        if ($pool === 0 || $unidades === []) {
            return [];
        }

        $pesos = [];

        foreach ($unidades as $unidad) {
            $pesos[(int) $unidad['numero']] = max(0, $this->minutosPedagogicos($unidad['horas_sugeridas'] ?? 0));
        }

        $sumaPesos = array_sum($pesos);
        $cupos = [];
        $numeros = array_keys($pesos);

        if ($sumaPesos === 0) {
            $base = intdiv($pool, count($numeros));
            $resto = $pool % count($numeros);

            foreach ($numeros as $indice => $numero) {
                $cupos[$numero] = $base + ($indice < $resto ? 1 : 0);
            }

            return $cupos;
        }

        $asignado = 0;

        foreach ($numeros as $numero) {
            $cupos[$numero] = intdiv($pesos[$numero] * $pool, $sumaPesos);
            $asignado += $cupos[$numero];
        }

        $resto = $pool - $asignado;
        $indice = 0;

        while ($resto > 0) {
            $cupos[$numeros[$indice % count($numeros)]]++;
            $resto--;
            $indice++;
        }

        return $cupos;
    }

    /**
     * @param  list<string>  $nombres
     * @return list<array{nombre: string, minutos: int}>
     */
    private function repartirNombres(array $nombres, int $total): array
    {
        if ($nombres === []) {
            return [];
        }

        $base = intdiv(max(0, $total), count($nombres));
        $resto = max(0, $total) % count($nombres);
        $piezas = [];

        foreach ($nombres as $indice => $nombre) {
            $piezas[] = [
                'nombre' => $nombre,
                'minutos' => $base + ($indice < $resto ? 1 : 0),
            ];
        }

        return $piezas;
    }

    /**
     * @param  array<string, array<string, mixed>>  $porFecha
     * @param  array<string, mixed>  $bloque
     */
    private function agregarBloque(array &$porFecha, string $fecha, array $bloque): void
    {
        $porFecha[$fecha]['bloques'][] = $bloque;
    }

    /**
     * @return array<string, mixed>
     */
    private function bloqueEvaluacion(string $tipo, int $minutos, ?int $unidad): array
    {
        $descripcion = match ($tipo) {
            'EVALUACION' => 'Evaluación de la unidad '.($unidad ?? ''),
            'EVALUACION_RECUPERATIVA' => 'Prueba recuperativa',
            'EXAMEN_1' => 'Examen 1',
            'EXAMEN_REPETICION' => 'Examen de repetición',
            default => '',
        };

        $bloque = [
            'orden' => 1,
            'tipo' => $tipo,
            'duracion_minutos' => $minutos,
            'descripcion' => $descripcion,
        ];

        if ($unidad !== null) {
            $bloque['unidad'] = $unidad;
        }

        return $bloque;
    }

    /**
     * @param  list<array{fecha: string, duracion_minutos: int, minutos_disponibles_para_clases: int}>  $jornadas
     * @param  list<array<string, mixed>>  $unidades
     * @return list<array<string, mixed>>
     */
    private function marcarUnidadesPorFecha(array $jornadas, array $unidades): array
    {
        $pendientes = array_map(fn (array $unidad): array => [
            'numero' => (int) $unidad['numero'],
            'restante' => (int) $unidad['minutos_asignados'],
        ], $unidades);
        $indice = 0;

        foreach ($jornadas as &$jornada) {
            $jornada['unidades_de_clase'] = [];
            $libre = (int) $jornada['minutos_disponibles_para_clases'];

            if ($libre <= 0 || $pendientes === []) {
                continue;
            }

            while ($libre > 0 && $indice < count($pendientes)) {
                $numero = $pendientes[$indice]['numero'];

                if (! in_array($numero, $jornada['unidades_de_clase'], true)) {
                    $jornada['unidades_de_clase'][] = $numero;
                }

                if ($pendientes[$indice]['restante'] > $libre) {
                    $pendientes[$indice]['restante'] -= $libre;
                    $libre = 0;
                } else {
                    $libre -= $pendientes[$indice]['restante'];
                    $pendientes[$indice]['restante'] = 0;
                    $indice++;
                }
            }

            $ultima = $pendientes === [] ? null : $pendientes[array_key_last($pendientes)]['numero'];

            if ($libre > 0 && $ultima !== null && ! in_array($ultima, $jornada['unidades_de_clase'], true)) {
                $jornada['unidades_de_clase'][] = $ultima;
            }
        }

        unset($jornada);

        return $jornadas;
    }

    /**
     * @param  list<string>  $bloqueadas
     * @return list<array{fecha: string, duracion_minutos: int, minutos_disponibles_para_clases: int}>
     */
    private function jornadas(Asignatura $asignatura, array $bloqueadas): array
    {
        return $asignatura->clases
            ->where('sin_clase', false)
            ->groupBy(fn ($clase): string => $clase->fecha->toDateString())
            ->sortKeys()
            ->map(function ($clases, string $fecha) use ($bloqueadas): array {
                $minutos = $clases->sum(function ($clase): int {
                    if ($clase->hora_inicio === null || $clase->hora_termino === null) {
                        return 0;
                    }

                    [$horaInicio, $minutoInicio] = array_map('intval', explode(':', substr((string) $clase->hora_inicio, 0, 5)));
                    [$horaTermino, $minutoTermino] = array_map('intval', explode(':', substr((string) $clase->hora_termino, 0, 5)));

                    return max(0, (($horaTermino * 60) + $minutoTermino) - (($horaInicio * 60) + $minutoInicio));
                });

                $duracion = (int) $minutos;

                return [
                    'fecha' => $fecha,
                    'duracion_minutos' => $duracion,
                    'minutos_disponibles_para_clases' => in_array($fecha, $bloqueadas, true) ? 0 : $duracion,
                ];
            })
            ->values()
            ->all();
    }

    private function minutosPedagogicos(mixed $horas): int
    {
        if (! is_numeric($horas)) {
            return 0;
        }

        return (int) round(((float) $horas) * AsignaturaClase::MINUTOS_HORA_PEDAGOGICA);
    }

    /**
     * @return list<string>
     */
    private function textos(mixed $valor): array
    {
        if (is_string($valor)) {
            return trim($valor) === '' ? [] : [trim($valor)];
        }

        if (! is_array($valor)) {
            return [];
        }

        return array_values(array_filter(array_map(
            fn (mixed $item): string => is_scalar($item) ? trim((string) $item) : '',
            $valor,
        )));
    }
}
